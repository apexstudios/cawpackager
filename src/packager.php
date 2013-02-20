<?php
use YamwLibs\Libs\Cli\Cli;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$opts = $args[1];
$arguments = $args[0];

if (count($arguments) === 0) { // Help!
    echo <<<EOT
   Usage: packager pack <svn_repo_url>

       This always exports the latest version and zips it up for distribution.

   More to come in the following weeks / months!
EOT;
}

if ($arguments[0] == "pack" && isset($arguments[1])) { // Do the packaging here
    $path = YamwLibs\Functions\TmpFunc::tempdir(sys_get_temp_dir());

    try {
        $exportCmd = new YamwLibs\Libs\Vcs\Svn\Commands\SvnExportCommand($path, $arguments[1]);

        if (isset($arguments[2]) && (int)$arguments[1] !== 0) {
            $exportCmd->rev((int)$arguments[1]);
        }

        $output = $exportCmd->runCommand();
        Cli::notice("Exported repo!");

        $parsedOutput = YamwLibs\Libs\Vcs\Svn\SvnParser::parseChangelistOutput($output);
        $addedFiles = $parsedOutput["added"];

        foreach ($addedFiles as &$value) {
            $value = $path . "/" . $value;
        }

        $zipPath = "CaWPackageZip.zip";
        Cli::notice("Attempting to create zip file at " . $zipPath);

        $zipFile = new ZipArchive();
        $zipOpen = $zipFile->open($zipPath, ZipArchive::CREATE);
        \YamwLibs\Libs\Archives\Zip::folderToZip($path, $zipFile);
        $zipClose = $zipFile->close();

        // $zipCreated = \YamwLibs\Libs\Archives\Zip::createZip($addedFiles, $zipPath);

        if ($zipClose | $zipOpen) {
            // $zipCwdPath = getcwd() . "/CaWPackageZip.zip";
            // copy($path . "/CaWPackageZip.zip", $zipCwdPath);
            Cli::success("Exported repository to zip file " . $zipPath);
        } else {
            print_r($addedFiles);
            Cli::fatal("Could not create Zip archive!");
        }
    } catch (Exception $e) {
        // Stub
        echo $e->getMessage();
    }

    // Finally delete the temporary directory
    \YamwLibs\Functions\FileFunc::delTree($path);
} else {
    Cli::fatal("Subcommand not found / valid!");
}
