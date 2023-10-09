'use strict'
const shell = require('shelljs');
const fs = require('fs');
const archiver = require('archiver');

const pluginSlug = 'airwallex-online-payments-gateway';

// configs 
const releaseDir = 'release';
const targetDir = `${releaseDir}/${pluginSlug}`;
const filesToInclude = [
	'assets',
	'build',
	'html',
	'includes',
	'airwallex-online-payments-gateway.php',
    'license.txt',
	'readme.txt',
	'uninstall.php',
];

// start with a fresh release directory
shell.rm('-rf', releaseDir);
shell.mkdir(releaseDir);
shell.mkdir(targetDir);

// remove the 'hidden' source maps
shell.rm('build/*.map');

// copy required directories to the release dir
shell.cp('-Rf', filesToInclude, targetDir);

// create zip file
const output = fs.createWriteStream(`${releaseDir}/${pluginSlug}.zip`);
const archive = archiver('zip', {zlib: {level: 9}});

output.on('close', function() {
    console.log(`Done, release is built in the ${releaseDir} directory.`);
});

archive.on('error', (error) => {
    console.error(`An error occurred during the release: ${error}`);
});

// pipe archive data to the file
archive.pipe(output);

// append files from target dir and naming it with plugin slug within the archive
archive.directory(targetDir, pluginSlug);

// finalize the archive
archive.finalize();