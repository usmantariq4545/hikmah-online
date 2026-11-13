<?php
// 015 — Moodle Universal Cache on Redis: application caches go to Redis; sessions already use
// Redis through config.php. Request caches stay in memory.
require(__DIR__ . '/lib.php');

$host = hk_env('REDIS_HOST', 'redis');
$storename = 'hikmah_redis';

$factory = cache_factory::instance();
$config = $factory->create_config_instance();
$stores = $config->get_all_stores();

if (!isset($stores[$storename])) {
    $writer = cache_config_writer::instance();
    $writer->add_store_instance($storename, 'redis', [
        'server' => $host . ':' . hk_env('REDIS_PORT', 6379),
        'prefix' => 'hikmah_' . hk_env('SITE_ENV', 'dev') . '_muc_',
        'password' => '',
        'serializer' => 1, // PHP serializer.
        'compressor' => 0,
    ]);
    hk_changed('Redis cache store added');
}

$mappings = cache_config::instance()->get_mode_mappings();
$applicationstore = '';
foreach ($mappings as $mapping) {
    if ((int) $mapping['mode'] === cache_store::MODE_APPLICATION) {
        $applicationstore = $mapping['store'];
        break;
    }
}
if ($applicationstore !== $storename) {
    cache_config_writer::instance()->set_mode_mappings([
        cache_store::MODE_APPLICATION => [$storename],
        cache_store::MODE_SESSION => ['default_session'],
        cache_store::MODE_REQUEST => ['default_request'],
    ]);
    hk_changed('application caches mapped to Redis');
}

hk_done(__FILE__);
