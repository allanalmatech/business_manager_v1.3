/// A call either worked or it did not. Failures carry a message meant for the
/// person holding the phone, never a stack trace or an HTTP code.
sealed class Result<T> {
  const Result();

  bool get ok => this is Ok<T>;
  T? get valueOrNull => this is Ok<T> ? (this as Ok<T>).value : null;
  String? get errorOrNull => this is Err<T> ? (this as Err<T>).message : null;

  R fold<R>(R Function(T value) onOk, R Function(Err<T> err) onErr) =>
      this is Ok<T> ? onOk((this as Ok<T>).value) : onErr(this as Err<T>);
}

class Ok<T> extends Result<T> {
  const Ok(this.value);
  final T value;
}

class Err<T> extends Result<T> {
  const Err(this.message, {this.offline = false, this.unauthenticated = false, this.retryable = true});

  final String message;

  /// The phone could not reach the server. The work is queued, not lost.
  final bool offline;

  /// The server no longer accepts this device's token.
  final bool unauthenticated;

  /// Whether trying again later could succeed.
  final bool retryable;
}
