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
        $zipCreated = \YamwLibs\Libs\Archives\Zip::createZip($addedFiles, $zipCwdPath);

        var_dump($zipCreated);

        if ($zipCreated) {
            // copy($zipPath, $zipCwdPath);
            Cli::success("Exported repository to zip file " . $zipCwdPath);
        } else {
            print_r($zipCreated);
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
