<?php

include('../../connection.php');
include('../../queryUtils.php');
header('Content-type: application/vnd.geo+json');
$c = 0;
http_response_code(500);
$res = runPreparedQuery($conn, $c++, 
        "SELECT jsonb_build_object(
        'type',     'FeatureCollection',
        'features', jsonb_agg(features.feature)
        ) as res
        FROM (
          SELECT jsonb_build_object(
            'type',       'Feature',
            'id',         gid,
            'geometry',   ST_AsGeoJSON(geom)::jsonb,
            'properties', to_jsonb(inputs) - 'gid' - 'geom'
          ) AS feature
          FROM (SELECT *, ST_AsGeoJSON(ST_FlipCoordinates(centroid)) as centr FROM province) AS inputs) AS features;", []);
http_response_code(200);

$r = pg_fetch_all($res['data']);
echo ($r[0]['res']);
