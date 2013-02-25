<?php
use YamwLibs\Libs\Cli\Cli;
use Aws\Common\Aws;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Enum\CannedAcl;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

if (!file_exists(__DIR__ . "/config.json")) {
    Cli::fatal("Run `php config.php` first!");
}

$configObject = json_decode(file_get_contents(__DIR__ . "/config.json"));
$configArray = array(
    'key'    => $configObject->key,
    'secret' => $configObject->secret,
    'region' => $configObject->region
);
$s3 = Aws::factory($configArray)->get('s3');

if (!isset($args[0][0])) {
    Cli::fatal("You must specify a file name!");
}

$fileName = $args[0][0];

$request = $s3->get("{$configObject->bucket}/{$fileName}");
$url = $s3->createPresignedUrl($request, '+3240 minutes');

ob_start();

Cli::output("New build had been assembled.");
Cli::output("Filename is: " . $fileName);

Cli::output("Link is:");
Cli::output($url);

Cli::output("");

Cli::output("Signing off!");

$arcParam = (object)array(
    "id" => "172",
    "comments" => ob_get_clean(),
);

$jsonBlob = json_encode($arcParam);
// $jsonBlob = str_replace('"', '\"', $jsonBlob);
$jsonBlob = str_replace("\\r", "", $jsonBlob);
// $jsonBlob = str_replace("\\n", " \\\\n ", $jsonBlob);
Cli::output($jsonBlob);

Cli::output("");
Cli::output("");

$command = "echo $jsonBlob | arc call-conduit maniphest.update";

Cli::output($command);

Cli::output("");
Cli::output("");

passthru($command);
