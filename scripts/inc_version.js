//
// Helper script to bump the version number
//
// TODO: a Node.js dependency in a PHP package is not ideal.
//
var fs = require('fs');
var path = require('path');
var fullpath = path.resolve(path.join(process.cwd(), 'composer.json'));
var fulldir = path.dirname(fullpath);
var json = require(fullpath);

var version = json.version || '1.0.0';
var newVersion = require('semver').inc(version, 'patch');
console.log(version + ' -> ' + newVersion + ' in composer.json');

json.version = newVersion;
fs.writeFileSync(fullpath, JSON.stringify(json, null, 4));
fs.writeFileSync(path.join(fulldir, 'VERSION'), newVersion);
fs.writeFileSync(path.join(fulldir, 'lib/Client/Version.php'),
    '<?php\ndefine(\'LIGHTSTEP_VERSION\', \'' + newVersion + '\');\n\n'
);
