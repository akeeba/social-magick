<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Classifier\Classifier;

define('_JEXEC', 1);

/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once __DIR__ . '/../../component/backend/vendor/autoload.php';
$autoloader->addPsr4(
	'Akeeba\\Component\\SocialMagick\\Administrator\\Library\\ViolaJones\\',
	[
		__DIR__ . '/../../component/backend/src/Library/ViolaJones',
	]
);

$di       = new DirectoryIterator(__DIR__);
$basePath = __DIR__ . '/../../component/backend/src/Library/ViolaJones/models';

/** @var DirectoryIterator $file */
foreach ($di as $file)
{
	if (!$file->isFile() || $file->getExtension() !== 'xml')
	{
		continue;
	}

	$baseName = $file->getBasename('.xml');

	echo $baseName . "\n";

	$classifier = Classifier::fromXmlFile($file->getPathname());
	$json       = json_encode($classifier);
	$compressed = gzdeflate($json, 9, ZLIB_ENCODING_GZIP);
	$outPath    = $basePath . '/' . $baseName . '.json.gz';
	file_put_contents($outPath, $compressed);
}