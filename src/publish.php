<?php
use YamwLibs\Libs\Cli\Cli;
use Aws\Common\Aws;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Enum\CannedAcl;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$arguments = $args[0];

if (!isset($arguments[0])) {
    Cli::fatal("Please specify a file!");
}

$file = $arguments[0];

if (!file_exists(__DIR__ . "/config.json")) {
    Cli::fatal("Run `php config.php` first!");
}

$configObject = json_decode(file_get_contents(__DIR__ . "/config.json"));
$configArray = array(
    'key'    => $configObject->key,
    'secret' => $configObject->secret,
    'region' => $configObject->region
);

// $client = S3Client::factory($configArray);
$s3 = Aws::factory($configArray)->get('s3');

try {
    $s3->putObject(array(
        'Bucket' => $configObject->bucket,
        'Key'    => $file,
        'Body'   => fopen($file, 'r'),
        'ACL'    => CannedAcl::AUTHENTICATED_READ
    ));

    Cli::success("File $file was uploaded!");
    Cli::notice("I suspect that you may be able to download it here:");
    Cli::output("http://s3-eu-west-1.amazonaws.com/hcaw/" . $file);

    $disposition = "attachment; filename=\"{$file}\"";
    $request = $s3->get("{$configObject->bucket}/{$file}?response-content-disposition={$disposition}");
    $url = $s3->createPresignedUrl($request, '+240 minutes');
    echo "$url";
} catch (S3Exception $exc) {
    echo $exc->getTraceAsString() . PHP_EOL;
    Cli::fatal("Upload failed!");
}

// Delete the file
unlink($file);
