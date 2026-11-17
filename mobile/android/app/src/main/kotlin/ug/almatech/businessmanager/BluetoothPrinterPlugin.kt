package ug.almatech.businessmanager

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothSocket
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import androidx.core.content.ContextCompat
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.IOException
import java.util.UUID
import java.util.concurrent.Executors

/**
 * A plain Bluetooth serial connection to a receipt printer.
 *
 * The cheap thermal printers sold here expose the serial port profile and
 * accept ESC/POS bytes directly, so this opens an RFCOMM socket, writes what
 * Dart built, and closes it. Each receipt gets its own connection: slower than
 * keeping one open, but it survives the printer being switched off between
 * customers, which is how they are actually used.
 */
class BluetoothPrinterPlugin(private val activity: Activity) : MethodChannel.MethodCallHandler {

    companion object {
        const val CHANNEL = "ug.almatech.businessmanager/printer"
        private const val REQUEST_BLUETOOTH = 4201
        /** The well-known serial port profile id every one of these printers uses. */
        private val SPP_UUID: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val CONNECT_TIMEOUT_MS = 8000L
    }

    private val worker = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())
    private var pendingPairedResult: MethodChannel.Result? = null

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "paired" -> pairedWithPermission(result)
            "print" -> {
                val address = call.argument<String>("address")
                val bytes = call.argument<ByteArray>("bytes")
                if (address.isNullOrBlank() || bytes == null) {
                    result.error("bad_args", "A printer address and something to print are required.", null)
                    return
                }
                onWorker(result) { write(address, bytes); null }
            }
            else -> result.notImplemented()
        }
    }

    private fun pairedWithPermission(result: MethodChannel.Result) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S && !hasBluetoothPermission()) {
            if (pendingPairedResult != null) {
                result.error("no_permission", "Allow the app to use Bluetooth.", null)
                return
            }
            pendingPairedResult = result
            activity.requestPermissions(
                arrayOf(Manifest.permission.BLUETOOTH_SCAN, Manifest.permission.BLUETOOTH_CONNECT),
                REQUEST_BLUETOOTH
            )
            return
        }
        onWorker(result) { paired() }
    }

    /** Called by MainActivity after Android finishes the Nearby devices prompt. */
    fun onRequestPermissionsResult(requestCode: Int, grantResults: IntArray): Boolean {
        if (requestCode != REQUEST_BLUETOOTH) return false
        val result = pendingPairedResult
        pendingPairedResult = null
        if (result == null) return true

        if (grantResults.isNotEmpty() && grantResults.all { it == PackageManager.PERMISSION_GRANTED }) {
            onWorker(result) { paired() }
        } else {
            result.error("no_permission", "Allow the app to use Bluetooth to see paired printers.", null)
        }
        return true
    }

    /** Bluetooth work must never run on the UI thread: a stalled printer would freeze the till. */
    private fun onWorker(result: MethodChannel.Result, work: () -> Any?) {
        worker.execute {
            try {
                val value = work()
                main.post { result.success(value) }
            } catch (e: PrinterError) {
                main.post { result.error(e.code, e.message, null) }
            } catch (e: Throwable) {
                main.post { result.error("failed", e.message ?: "Printing failed.", null) }
            }
        }
    }

    private class PrinterError(val code: String, override val message: String) : Exception(message)

    private fun adapter(): BluetoothAdapter {
        val manager = activity.getSystemService(android.content.Context.BLUETOOTH_SERVICE) as? android.bluetooth.BluetoothManager
        val adapter = manager?.adapter ?: throw PrinterError("no_bluetooth", "This phone has no Bluetooth.")
        if (!adapter.isEnabled) throw PrinterError("bluetooth_off", "Bluetooth is switched off.")
        return adapter
    }

    private fun hasBluetoothPermission(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return true
        return ContextCompat.checkSelfPermission(activity, Manifest.permission.BLUETOOTH_SCAN) ==
                PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(activity, Manifest.permission.BLUETOOTH_CONNECT) ==
                PackageManager.PERMISSION_GRANTED
    }

    /** Android 12 and newer ask for BLUETOOTH_CONNECT at runtime; older versions grant it at install. */
    private fun requirePermission() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        val granted = ContextCompat.checkSelfPermission(activity, Manifest.permission.BLUETOOTH_CONNECT) ==
            PackageManager.PERMISSION_GRANTED
        if (!granted) throw PrinterError("no_permission", "Allow the app to use Bluetooth.")
    }

    @SuppressLint("MissingPermission")
    private fun paired(): List<Map<String, Any>> {
        requirePermission()
        return adapter().bondedDevices.orEmpty().map {
            mapOf(
                "name" to (it.name ?: "Printer"),
                "address" to it.address,
                "bonded" to true
            )
        }
    }

    @SuppressLint("MissingPermission")
    private fun write(address: String, bytes: ByteArray) {
        requirePermission()
        val adapter = adapter()
        val device: BluetoothDevice = adapter.bondedDevices.orEmpty().firstOrNull { it.address == address }
            ?: throw PrinterError("not_paired", "That printer is not paired with this phone.")

        // Scanning while connecting makes the connection fail on many phones.
        if (adapter.isDiscovering) adapter.cancelDiscovery()

        var socket: BluetoothSocket? = null
        try {
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID)
            connectWithTimeout(socket)
            socket.outputStream.use { out ->
                // Written in small pieces: the printer's buffer is tiny and a
                // long receipt sent in one go is dropped halfway on some models.
                var offset = 0
                while (offset < bytes.size) {
                    val end = minOf(offset + 512, bytes.size)
                    out.write(bytes, offset, end - offset)
                    out.flush()
                    offset = end
                    Thread.sleep(20)
                }
                // Let the paper finish moving before the connection closes
                Thread.sleep(250)
            }
        } catch (e: IOException) {
            throw PrinterError(if (socket?.isConnected == true) "write_failed" else "connect_failed",
                e.message ?: "Could not reach the printer.")
        } finally {
            try { socket?.close() } catch (_: IOException) { }
        }
    }

    /** connect() can hang indefinitely on a printer that is off, so it is given a deadline. */
    private fun connectWithTimeout(socket: BluetoothSocket) {
        val error = arrayOfNulls<IOException>(1)
        val thread = Thread {
            try {
                socket.connect()
            } catch (e: IOException) {
                error[0] = e
            }
        }
        thread.start()
        thread.join(CONNECT_TIMEOUT_MS)
        if (thread.isAlive) {
            try { socket.close() } catch (_: IOException) { }
            throw PrinterError("connect_failed", "The printer did not answer. Check it is on and nearby.")
        }
        error[0]?.let { throw it }
    }
}
