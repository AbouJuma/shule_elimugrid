import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:elimugrid_app/core/network/road_router.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:latlong2/latlong.dart';

class _Adapter implements HttpClientAdapter {
  _Adapter(this.respond);

  final ResponseBody Function(RequestOptions) respond;
  final List<String> requests = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options.uri.toString());

    return respond(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(Object body, [int status = 200]) => ResponseBody.fromString(jsonEncode(body), status, headers: {Headers.contentTypeHeader: ['application/json']});

const _points = [LatLng(-6.73, 39.22), LatLng(-6.744, 39.228), LatLng(-6.761, 39.242)];

RoadRouter _router(_Adapter adapter, {String base = 'https://osrm.test'}) => RoadRouter(dio: Dio()..httpClientAdapter = adapter, baseUrl: base);

void main() {
  test('asks for lng,lat pairs in order and returns the road line as lat/lng', () async {
    final adapter = _Adapter((_) => _json({'code': 'Ok', 'routes': [{'geometry': {'coordinates': [[39.22, -6.73], [39.224, -6.737], [39.228, -6.744]]}}]}));
    final line = await _router(adapter).route(_points);

    expect(adapter.requests.single, startsWith('https://osrm.test/route/v1/driving/39.22,-6.73;39.228,-6.744;39.242,-6.761'));
    expect(line, hasLength(3));
    expect(line![1], const LatLng(-6.737, 39.224));
  });

  test('the same stops are only requested once', () async {
    final adapter = _Adapter((_) => _json({'code': 'Ok', 'routes': [{'geometry': {'coordinates': [[39.22, -6.73], [39.228, -6.744]]}}]}));
    final router = _router(adapter);

    await Future.wait([router.route(_points), router.route(_points)]);
    await router.route(_points);

    expect(adapter.requests, hasLength(1));
    expect(router.cached(_points), isNotNull);
  });

  test('a failure gives null (straight lines stay) and is retried next time', () async {
    var fail = true;
    final adapter = _Adapter((_) => fail ? _json({'message': 'down'}, 500) : _json({'code': 'Ok', 'routes': [{'geometry': {'coordinates': [[39.22, -6.73], [39.228, -6.744]]}}]}));
    final router = _router(adapter);

    expect(await router.route(_points), isNull);
    fail = false;
    expect(await router.route(_points), isNotNull);
    expect(adapter.requests, hasLength(2));
  });

  test('"NoRoute" and empty answers give null', () async {
    expect(await _router(_Adapter((_) => _json({'code': 'NoRoute', 'routes': []}))).route(_points), isNull);
    expect(await _router(_Adapter((_) => _json({'code': 'Ok', 'routes': []}))).route(_points), isNull);
  });

  test('it never calls out when turned off, or for fewer than two points, or for too many', () async {
    final adapter = _Adapter((_) => _json({'code': 'Ok', 'routes': []}));

    expect(await _router(adapter, base: '').route(_points), isNull);
    expect(await _router(adapter).route(_points.take(1).toList()), isNull);
    expect(await _router(adapter).route(List.filled(RoadRouter.maxWaypoints + 1, _points.first)), isNull);
    expect(adapter.requests, isEmpty);
  });
}
