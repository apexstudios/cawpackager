<?php
ob_start();

use YamwLibs\Libs\Cli\Cli;
use Aws\Common\Aws;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Enum\CannedAcl;

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    array_shift($argv);
    $args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

    $opts = $args[1];
    $arguments = $args[0];

    $oldCwd = getcwd();
    $configObject = json_decode(file_get_contents(__DIR__ . "/config.json"));

    $configArray = array(
        'key'    => $configObject->key,
        'secret' => $configObject->secret,
        'region' => $configObject->region
    );

    $s3 = Aws::factory($configArray)->get('s3');

    if (!$configObject) {
        throw new Exception("You have to run `php config.php` first!");
    }

    $path = YamwLibs\Functions\TmpFunc::tempdir(sys_get_temp_dir());
    Cli::notice("Using $path as our cwd.");

    $repoUrl = $configObject->repo;

    $exportCmd = new YamwLibs\Libs\Vcs\Svn\Commands\SvnExportCommand($path, $repoUrl . '" "CaW/');

    if (isset($opts["r"]) && (int)$opts["r"] !== 0) {
        $revision = (int)$opts["r"];
    } else {
        $infoCommand = new \YamwLibs\Libs\Vcs\Svn\Commands\SvnInfoCommand($path, $repoUrl);
        $infoOutput = YamwLibs\Libs\Vcs\Svn\SvnParser::parseInfoOutput(
            $infoCommand->runCommand()
        );

        $revision = (int)$infoOutput["Revision"];
    }
    $exportCmd->rev($revision);
    Cli::notice("Exporting the repository at revision " . $revision);

    $exportOutput = $exportCmd->runCommand();
    Cli::notice("Successfully exported files from the repository!");

    $parsedOutput = YamwLibs\Libs\Vcs\Svn\SvnParser::parseChangelistOutput($exportOutput);
    $addedFiles = $parsedOutput["added"];

    chdir($oldCwd);

    $fileName = "CaWPackageZip.rev{$revision}.zip";
    $zipPath = $path . DIRECTORY_SEPARATOR . $fileName;
    Cli::notice("Attempting to create zip file at " . $zipPath);

    //create the archive
    $zip = new \ZipArchive();
    $zipOpen = $zip->open($zipPath, \ZipArchive::CREATE);
    if ($zipOpen !== true) {
        var_dump($zipOpen);
        throw new Exception("Could not open zip file!");
    }

    Cli::output("");

    $fileList = array();
    foreach ($addedFiles as $file) {
        $fullFileName = $path . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($fullFileName) || is_dir($fullFileName)) {
            continue;
        }

        $fileList[] = $file;
        $zip->addFile($fullFileName, $file);
    }

    Cli::output("");
    Cli::notice('The zip archive contains ' . $zip->numFiles . ' files with a status of ' . $zip->status . PHP_EOL);

    $zipClose = $zip->close();

    if ($zipClose !== true) {
        var_dump($zipClose);
        throw new Exception("Error while closing zip archive.");
    }

    if (file_exists($zipPath) !== true) {
        throw new Exception("File could not be created!");
    }
    Cli::output("");
    Cli::success("Created zip file at " . $zipPath);

    // Finally delete the temporary directory
    \YamwLibs\Functions\FileFunc::delTree($path);

    $s3->putObject(array(
        'Bucket' => $configObject->bucket,
        'Key'    => $file,
        'Body'   => fopen($file, 'r'),
        'ACL'    => CannedAcl::AUTHENTICATED_READ
    ));

    $url = "http://s3-" . $configObject->region . ".amazonaws.com/" . $configObject->bucket . "/" . $fileName;

    Cli::success("File $file was uploaded!");
    Cli::notice("I suspect that you may be able to download it here:");
    Cli::output($url);

    $jsonBlob = json_encode(array(
        "url" => $url,
    ));

    Cli::output("");

    $arcCommand = "echo $jsonBlob | arc call-conduit packager.register";

    Cli::output($arcCommand);

    Cli::output("");

    $arcOutput = array();
    exec($arcCommand, $arcOutput);
} catch (S3Exception $exc) {
    echo $exc->getTraceAsString() . PHP_EOL;
    Cli::error("Upload failed!");
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    Cli::error("Packaging process failed!");
}

$obContents = ob_get_clean();

$logJsonBlob = json_encode(array(
    "time" => time(),
    "date" => date(DATE_RFC2822),
    "revision" => $revision,
    "zipPath" => $zipPath,
    "parsedFileList" => $parsedOutput,
    "takeFileList" => $addedFiles,
    "actualFileList" => $fileList,
    "totalOutput" => $obContents,
    "exportOutput" => $exportOutput,
    "repoUrl" => $repoUrl,
    "url" => $url,
    "arcInput" => $jsonBlob,
    "arcOutput" => $arcOutput,
    "arcCommand" => $arcCommand,
));

$s3->putObject(array(
    'Bucket' => $configObject->bucket,
    'Key'    => "logs/" . $logJsonBlob["date"] . "-" . microtime(true),
    'Body'   => $logJsonBlob,
    'ACL'    => CannedAcl::AUTHENTICATED_READ
));
