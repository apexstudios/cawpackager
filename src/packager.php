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

        chdir(__DIR__);

        $zipPath = $path . "/CaWPackageZip.zip";
        $zipCwdPath = getcwd() . "/CaWPackageZip.zip";
        Cli::notice("Attempting to create zip file at " . $zipCwdPath);

        if (!touch($zipCwdPath)) {
            Cli::fatal("File not writeable. Continueing is futile.");
        }

        //create the archive
        $zip = new \ZipArchive();
        $zipOpen = $zip->open($zipCwdPath, \ZipArchive::OVERWRITE);
        if ($zipOpen !== true) {
            var_dump($zipOpen);
            Cli::fatal("Could not open zip file!");
        }

        //add the files
        foreach ($addedFiles as $file) {
            $zip->addFile($file, $file);
        }
        //debug
        echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status . PHP_EOL;
        //close the zip -- done!
        $zipClose = $zip->close();

        if ($zipClose !== true) {
            var_dump($zipClose);
            Cli::fatal("Error while closing.");
        }

        if (file_exists($zipCwdPath) !== true) {
            Cli::fatal("File could not be created!");
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    // Finally delete the temporary directory
    \YamwLibs\Functions\FileFunc::delTree($path);
} else {
    Cli::fatal("Subcommand not found / valid!");
}
