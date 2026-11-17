package ug.almatech.businessmanager

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private lateinit var printerPlugin: BluetoothPrinterPlugin

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        printerPlugin = BluetoothPrinterPlugin(this)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, BluetoothPrinterPlugin.CHANNEL)
            .setMethodCallHandler(printerPlugin)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        if (::printerPlugin.isInitialized && printerPlugin.onRequestPermissionsResult(requestCode, grantResults)) return
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
    }
}
