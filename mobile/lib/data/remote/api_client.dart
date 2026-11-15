import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/result.dart';

/// Talks to /api/v1. Every failure comes back as an Err with a message a
/// cashier can understand, and says whether it is worth trying again.
class ApiClient {
  ApiClient({http.Client? client, String? baseUrl})
      : _client = client ?? http.Client(),
        baseUrl = (baseUrl ?? AppConfig.baseUrl).replaceAll(RegExp(r'/+$'), '');

  final http.Client _client;
  final String baseUrl;
  String? _token;

  set token(String? value) => _token = value;
  String? get token => _token;

  Uri _uri(String route, [Map<String, String>? query]) =>
      Uri.parse('$baseUrl/api/v1/index.php').replace(queryParameters: <String, String>{'r': route, ...?query});

  Map<String, String> _headers({bool json = true}) => <String, String>{
        if (json) 'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
        if (_token != null) 'X-Auth-Token': _token!,
      };

  Future<Result<Map<String, Object?>>> get(String route, {Map<String, String>? query}) =>
      _send(() => _client.get(_uri(route, query), headers: _headers(json: false)));

  Future<Result<Map<String, Object?>>> post(String route, [Map<String, Object?>? body]) =>
      _send(() => _client.post(_uri(route), headers: _headers(), body: jsonEncode(body ?? <String, Object?>{})));

  /// Multipart upload, used for product photos.
  Future<Result<Map<String, Object?>>> upload(String route, Map<String, String> fields, String fileField, String filePath) {
    return _send(() async {
      final http.MultipartRequest req = http.MultipartRequest('POST', _uri(route))
        ..fields.addAll(fields)
        ..headers.addAll(_headers(json: false))
        ..files.add(await http.MultipartFile.fromPath(fileField, filePath));
      final http.StreamedResponse streamed = await _client.send(req);
      return http.Response.fromStream(streamed);
    });
  }

  Future<Result<Map<String, Object?>>> _send(Future<http.Response> Function() run) async {
    try {
      final http.Response res = await run().timeout(AppConfig.requestTimeout);
      Map<String, Object?> body;
      try {
        final Object? decoded = jsonDecode(res.body);
        body = decoded is Map<String, Object?> ? decoded : <String, Object?>{};
      } catch (_) {
        // A sign-in page or a hosting error page instead of JSON
        return Err<Map<String, Object?>>(
          res.statusCode >= 500 ? 'The server had a problem. Try again shortly.' : 'The server sent an unexpected reply.',
          retryable: res.statusCode >= 500,
        );
      }

      if (res.statusCode == 401) {
        return Err<Map<String, Object?>>(
          (body['error'] as String?) ?? 'Sign in again.',
          unauthenticated: true,
          retryable: false,
        );
      }
      if (body['ok'] == true) return Ok<Map<String, Object?>>(body);

      final String message = (body['error'] as String?) ?? (body['message'] as String?) ?? 'That did not work.';
      // 4xx will not succeed on a retry; 429 and 5xx will
      final bool retryable = res.statusCode >= 500 || res.statusCode == 429;
      return Err<Map<String, Object?>>(message, retryable: retryable);
    } on TimeoutException {
      return const Err<Map<String, Object?>>('The server took too long to answer.', offline: true);
    } on SocketException {
      return const Err<Map<String, Object?>>('No connection to the shop server.', offline: true);
    } on http.ClientException {
      return const Err<Map<String, Object?>>('The connection dropped.', offline: true);
    } catch (e) {
      return Err<Map<String, Object?>>('Something went wrong talking to the server.', retryable: true);
    }
  }

  void close() => _client.close();
}
