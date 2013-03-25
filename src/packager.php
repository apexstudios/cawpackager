<?php
ob_start();
ob_implicit_flush(true);

require_once __DIR__ . '/../vendor/autoload.php';

use YamwLibs\Libs\Cli\Cli;
use Aws\Common\Aws;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Enum\CannedAcl;

try {
    $oldCwd = getcwd();
    $configObject = json_decode(file_get_contents(__DIR__ . "/config.json"));

    if (!$configObject) {
        throw new Exception("You have to run `php config.php` first!");
    }

    $configArray = array(
        'key'    => $configObject->key,
        'secret' => $configObject->secret,
        'region' => $configObject->region
    );

    $s3 = Aws::factory($configArray)->get('s3');

    $configArray['region'] = 'us-east-1';
    $sqs = Aws::factory($configArray)->get('sqs');

    $loopCount = 1;
    for (;;) {
        $result = $sqs->receiveMessage(array(
            'QueueUrl'          => 'https://sqs.us-east-1.amazonaws.com/830649155612/PackagingQueue',
            'WaitTimeSeconds'   => 20,
        ));

        if (!count($result->getPath('Messages/*/Body')) && $loopCount < 50) {
            $loopCount++;
            continue;
        } elseif (count($result->getPath('Messages/*/Body'))) {
            break;
        } else {
            throw new Exception("Did not find any messages in queue.");
        }
    }
    $result = $result->getPath('Messages/*');
    $msgBody = json_decode($result['Body']);

    $path = YamwLibs\Functions\TmpFunc::tempdir(sys_get_temp_dir());
    Cli::notice("Using $path as our cwd.");

    $repoUrl = $configObject->repo . $msgBody->url;

    $exportCmd = new YamwLibs\Libs\Vcs\Svn\Commands\SvnExportCommand($path, $repoUrl . '" "CaW/');
    $exportCmd->rev($msgBody->revision);
    Cli::notice("Exporting the repository at revision " . $msgBody->revision);

    $retVal = -55;
    $exportOutput = $exportCmd->runCommand($retVal);

    if ($retVal === 0) {
        Cli::notice("Successfully exported files from the repository!");
    } else {
        var_dump($exportOutput);
        throw new Exception("Failed to check out files from repository!");
    }

    $parsedOutput = YamwLibs\Libs\Vcs\Svn\SvnParser::parseChangelistOutput($exportOutput);
    $addedFiles = $parsedOutput["added"];

    chdir($oldCwd);

    $fileName = $msgBody->filename;
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

    $s3->putObject(array(
        'Bucket' => $configObject->bucket,
        'Key'    => $fileName,
        'Body'   => fopen($zipPath, 'r'),
        'ACL'    => CannedAcl::AUTHENTICATED_READ
    ));

    $url = "http://s3-" . $configObject->region . ".amazonaws.com/" . $configObject->bucket . "/" . $fileName;

    Cli::success("File $file was uploaded!");
    Cli::notice("I suspect that you may be able to download it here:");
    Cli::output($url);

    $descriptorspec = array(
        0 => array(
            "pipe",
            "r"), // stdin is a pipe that the child will read from
        1 => array(
            "pipe",
            "w"), // stdout is a pipe that the child will write to
        2 => array(
            "pipe",
            "w") // stderr is a file to write to
    );

    $pipes = array();
    $arcProcess = proc_open('arc call-conduit packager.register', $descriptorspec, $pipes);

    if (is_resource($arcProcess)) {
        Cli::notice("Successfully opened arc!");
        $jsonBlob = json_encode(array(
            "url" => $url,
        ));
        fwrite($pipes[0], $jsonBlob);
        fclose($pipes[0]);

        $arcOutput = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        echo $arcOutput;

        $arcError = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $arcReturn = proc_close($arcProcess);

        Cli::output($arcOutput);
        echo "command returned $arcReturn";
    } else {
        throw new Exception("Failed to call arc!");
    }

    // Finally delete the temporary directory
    \YamwLibs\Functions\FileFunc::delTree($path);
} catch (S3Exception $exc) {
    echo $exc->getMessage() . PHP_EOL;
    Cli::error("Upload failed!");
} catch (Exception $exc) {
    echo $exc->getMessage() . PHP_EOL;
    Cli::error("Packaging process failed!");
}

$obContents = ob_get_clean();
echo $obContents;

$logJsonBlob = json_encode(array(
    "time"           => time(),
    "date"           => date(DATE_RFC2822),
    "revision"       => $msgBody->revision,
    "zipPath"        => $zipPath,
    "actualFileList" => $fileList,
    "totalOutput"    => $obContents,
    "exportOutput"   => $exportOutput,
    "repoUrl"        => $repoUrl,
    "phabUrl"        => $configObject->phabricator,
    "url"            => $url,
    "arcInput"       => $jsonBlob,
    "arcReturn"      => $arcReturn,
    "arcOutput"      => $arcOutput,
    "arcError"       => $arcError,
    ));

$s3->putObject(array(
    'Bucket' => $configObject->bucket,
    'Key'    => "logs/" . date(DATE_RFC2822) . ".json",
    'Body'   => $logJsonBlob,
    'ACL'    => CannedAcl::AUTHENTICATED_READ
));
