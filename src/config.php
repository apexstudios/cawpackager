<?php
use YamwLibs\Libs\Cli\Cli;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$arguments = $args[0];

if (isset($arguments[0]) && $arguments[0] == "help") {
    echo <<<EOT
   Usage: php config.php <bucket-name> <bucket-region> <key> <secret>
EOT;
    exit(0);
}

function read()
{
    return trim(fread(STDIN, 99));
}

Cli::output("Please tell me the bucket name:");
$bucketName = read();

Cli::output("Please tell me the region:");
$bucketRegion = read();

Cli::output("Please tell me your AWS Access Key:");
$key = read();

Cli::output("Please tell me your secret (and your PIN, too):");
$secret = read();

$configObj = (object)array(
    "bucket" => $bucketName,
    "region" => $bucketRegion,
    "key"    => $key,
    "secret" => $secret,
);

if (file_put_contents(__DIR__ . "/config.json", json_encode($configObj))) {
    Cli::success("Config saved.");
} else {
    Cli::fatal("Config could not be saved.");
}
