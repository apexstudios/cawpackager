<?php
use YamwLibs\Libs\Cli\Cli;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$opts = $args[1];
$arguments = $args[0];

$oldCwd = getcwd();
$configObject = json_decode(file_get_contents(__DIR__ . "/config.json"));

if (!$configObject) {
    Cli::fatal("You have to run `php config.php` first!");
}

if (isset($arguments[0]) && $arguments[0] == "help") { // Help!
    echo <<<EOT
   Usage: packager pack <svn_repo_url> [revision]

       This always exports the latest version and zips it up for distribution.

   More to come in the following weeks / months!
EOT;
    exit(0);
}

$path = YamwLibs\Functions\TmpFunc::tempdir(sys_get_temp_dir());
Cli::notice("Using $path as our cwd.");

$repoUrl = $configObject->repo;

try {
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

    $output = $exportCmd->runCommand();
    Cli::notice("Successfully exported files from the repository!");

    $parsedOutput = YamwLibs\Libs\Vcs\Svn\SvnParser::parseChangelistOutput($output);
    $addedFiles = $parsedOutput["added"];

    chdir($oldCwd);

    $fileName = "CaWPackageZip.rev{$revision}.zip";
    $zipPath = $path . DIRECTORY_SEPARATOR . $fileName;
    $zipCwdPath = getcwd() . DIRECTORY_SEPARATOR . $fileName;
    Cli::notice("Attempting to create zip file at " . $zipPath);

    //create the archive
    $zip = new \ZipArchive();
    $zipOpen = $zip->open($zipPath, \ZipArchive::CREATE);
    if ($zipOpen !== true) {
        var_dump($zipOpen);
        Cli::fatal("Could not open zip file!");
    }

    Cli::output("");

    foreach ($addedFiles as $file) {
        $fullFileName = $path . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($fullFileName) || is_dir($fullFileName)) {
            continue;
        }

        Cli::output("Adding file: " . $file);
        $zip->addFile($fullFileName, $file);
    }

    Cli::output("");
    Cli::notice('The zip archive contains ' . $zip->numFiles . ' files with a status of ' . $zip->status . PHP_EOL);

    $zipClose = $zip->close();

    if ($zipClose !== true) {
        var_dump($zipClose);
        Cli::fatal("Error while closing.");
    }

    if (file_exists($zipPath) !== true) {
        Cli::fatal("File could not be created!");
    }
    Cli::output("");
    Cli::success("Created zip file at " . $zipPath);

    $copyStatus = copy($zipPath, $zipCwdPath);
    if ($copyStatus === true) {
        Cli::notice("File copied to " . $zipCwdPath);
    } else {
        Cli::error("File could not be copied to " . $zipCwdPath);
        Cli::output("Do it on your own.");
    }

    echo $fileName;
} catch (Exception $e) {
    echo $e->getMessage();
}

// Finally delete the temporary directory
\YamwLibs\Functions\FileFunc::delTree($path);
