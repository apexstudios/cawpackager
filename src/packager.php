<?php
use YamwLibs\Libs\Cli\Cli;

require_once __DIR__ . '/../vendor/autoload.php';
array_shift($argv);
$args = \YamwLibs\Libs\Cli\CliArgs::parseArgv($argv);

$opts = $args[1];
$arguments = $args[0];

$oldCwd = getcwd();

if (count($arguments) === 0) { // Help!
    echo <<<EOT
   Usage: packager pack <svn_repo_url> [revision]

       This always exports the latest version and zips it up for distribution.

   More to come in the following weeks / months!
EOT;
}

if ($arguments[0] == "pack" && isset($arguments[1])) { // Do the packaging here
    $path = YamwLibs\Functions\TmpFunc::tempdir(sys_get_temp_dir());
    Cli::notice("Using $path as our cwd.");

    $repoUrl = $arguments[1];

    try {
        $exportCmd = new YamwLibs\Libs\Vcs\Svn\Commands\SvnExportCommand($path, $repoUrl . '" "CaW/');

        if (isset($arguments[2]) && (int)$arguments[2] !== 0) {
            $revision = (int)$arguments[2];
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

        $fileName = "/CaWPackageZip.rev{$revision}.zip";
        $zipPath = $path . $fileName;
        $zipCwdPath = getcwd() . $fileName;
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
        Cli::success("Successfully created zip file at " . $zipPath);

        $copyStatus = copy($zipPath, $zipCwdPath);
        if ($copyStatus === true) {
            Cli::notice("File copied to " . $zipCwdPath);
        } else {
            Cli::error("File could not be copied to " . $zipCwdPath);
            Cli::output("Do it on your own.");
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    // Finally delete the temporary directory
    \YamwLibs\Functions\FileFunc::delTree($path);
} else {
    Cli::fatal("Subcommand not found / valid!");
}
