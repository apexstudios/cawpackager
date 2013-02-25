<?php
use YamwLibs\Libs\Cli\Cli;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$arguments = $args[0];

if (isset($arguments[0]) && $arguments[0] == "help") {
    echo <<<EOT
   Usage: php config.php
EOT;
    exit(0);
}

function read()
{
    return trim(fread(STDIN, 99));
}

function readSomething($question)
{
    Cli::output($question);
    return read();
}

$bucketName = readSomething("Please tell me the bucket name:");

$bucketRegion = readSomething("Please tell me the region:");

$key = readSomething("Please tell me your AWS Access Key:");

$secret = readSomething("Please tell me your secret (and your PIN, too):");

$repoUrl = readSomething("Please tell me the location of the repository:");

$phabricatorUrl = readSomething("Please tell me the location of Phabricator:");

$configObj = (object)array(
    "bucket" => $bucketName,
    "region" => $bucketRegion,
    "key"    => $key,
    "secret" => $secret,
    "repo"   => $repoUrl,
    "phabricator" => $phabricatorUrl,
);

if (file_put_contents(__DIR__ . "/config.json", json_encode($configObj))) {
    Cli::success("Config saved.");
} else {
    Cli::fatal("Config could not be saved.");
}
