<?php
/**
 * Generate a graph of model dependencies for CakePHP application
 *
 * Usage:
 *
 * $ php -f graph.php > graph.dot && dot -Tpng graph.dot > graph.png
 *
 * Explanation:
 *
 * The script generates a description of all model relationships in dot format (GraphViz).  All models
 * are considered, both in app/models/ folder and in all app/plugins/SOMETHING/models/ folders.
 *
 * Note:
 *
 * The script relies on being in app/docs/ folder.  If you place it anywhere else, don't forget to 
 * correct the paths in constants below.
 *
 * TODO:
 *
 * - Rewrite GraphViz part using the GraphViz PEAR module
 * - Fix models that fall outside cluster due to aliasing (e.g.: $belongsTo => array('Owner' => array('className'=>'User')))
 *
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 */ 

define('PATH_TO_APP_MODELS', '/../models');
define('PATH_TO_PLUGINS', '/../plugins');
define('PATH_TO_SVNVERSION', '/usr/bin/svnversion');
define('UNKNOWN_REVISION', 'Unknown');

/**
 * Graph settings for each type of relationship.  Consult dot language documentation for more details.
 */
$relationSettings = array(
					'belongsTo' => array('dir'=>'forward', 'color'=>'green'), 
					'hasOne' => array('dir'=>'forward', 'color'=>'magenta'),
					'hasMany' => array('dir'=>'forward', 'color'=>'blue'),
					'hasAndBelongsToMany' => array('dir'=>'both', 'color'=>'red'),
);

/**
 * Empty Model class is needed to bypass fatal errors when loading models, since we don't
 * load the full CakePHP architecture.
 */
class Model {
}

/**
 * Find model paths based on current location
 *
 * @return array
 */
function getModelsDirs() {
	$result = array();

	// app/models
	$result[] = realpath(dirname(__FILE__) . PATH_TO_APP_MODELS);

	// plugins
	$pluginsDir = new DirectoryIterator(realpath(dirname(__FILE__) . PATH_TO_PLUGINS));
	foreach ($pluginsDir as $fileInfo) {
		if ($fileInfo->isDot()) {
			continue;
		}
		if ($fileInfo->isDir()) {
			$modelDir = $fileInfo->getPathname() . '/models';
			if (is_dir($modelDir)) {
				$result[] = $modelDir;
			}
		}
	}

	return $result;
}

/**
 * Get model name based on file name
 *
 * @param object $fileInfo
 * @return string
 */
function getModelName($fileInfo) {
	$result = ucfirst(preg_replace('/\.php$/i', '', $fileInfo->getFilename()));

	return $result;
}

/**
 * Check if filename follows the model naming convention
 *
 * @param object $fileInfo
 * @return boolean
 */
function isValidModel($fileInfo) {
	$result = false;
	if (preg_match('/^[A-z0-9]+\.php$/', $fileInfo->getFilename())) {
		$result = true;
	}
	return $result;
}


/**
 * Get two characters to use as targets in the legend
 *
 * @param string $last Last-used character
 * @return array
 */
function getTargets($last = null) {
	if (! $last) {
		$last = '@'; // Next ASCII table character is A
	}
	$first = chr(ord($last) + 1);
	$second = chr(ord($last) + 2);

	return array($first, $second);
}

/**
 * Convert an array of settings into dot string
 *
 * @param array $settings Associative array of settings
 * @return string
 */
function prepareSettings($settings) {
	$result = '';

	foreach ($settings as $key => $value) {
		$result .= sprintf("%s=\"%s\", ", $key, $value);
	}
	$result = preg_replace('/,\s$/', '', $result);
	if ($result) {
		$result = "[$result]";
	}

	return $result;
}

/**
 * Generate a relation string in dot format
 * 
 * @param string $from Source node 
 * @param string $to Destination node 
 * @param array $settings Array of edge settings
 */
function prepareRelation($from, $to, $settings) {
	$result = '';

	$settingsString = prepareSettings($settings);
	$result = sprintf("\t%s -> %s %s;\n", $from, $to, $settingsString);

	return $result;
}

/**
 * Generate a node string in dot format
 *
 * @param string $node Node name
 * @param array $settings Array of node settings
 */
function prepareNode($node, $settings) {
	$result = '';

	$settingsString = prepareSettings($settings);
	$result = sprintf("\t%s %s;\n", $node, $settingsString);

	return $result;
}


/**
 * Find out the current revision of the project
 *
 * @return string Revision string or UNKNOWN_REVISION if failed to determine
 */
function getRevision() {
	$result = UNKNOWN_REVISION;

	if (is_executable(PATH_TO_SVNVERSION)) {
		$result = trim(shell_exec(PATH_TO_SVNVERSION));
	}

	return $result;
}

/**
 * Print graph header
 */
function printGraphHead() {
	print "digraph models {\n";
	print "\tlabel=\"Model relationships (Date: " . date('Y-m-d H:i:s') . ", SVN: " . getRevision() . ")\";\n";
	print "\tlabelloc=\"t\";\n";
#	print "\trankdir=\"LR\";\n";
	print "\tnode [shape=\"box\"];\n";
}

/**
 * Print graph footer
 */
function printGraphTail() {
	print "}\n";
}

/**
 * Print graph legend
 *
 * @param array $relationSettings Show sample nodes with relations and explanations on the graph
 */
function printGraphLegend($relationSettings) {
	print "\tsubgraph clusterLegend {\n";
	print "\t\tlabel=\"Legend\";\n";
	print "\t\tstyle=\"filled\";\n";
	$second = '';
	foreach ($relationSettings as $type => $settings) {
		$settings['label'] = $type;
		list($first, $second) = getTargets($second);
		print "\t" . prepareRelation($first, $second, $settings);
	}
	print "\t}\n";

}

/**
 * Print node clusters in dot format
 *
 * To avoid misplacing nodes, first we generate a cluster for each model
 * location and put all models from that location as nodes of this cluster.
 *
 * @param array $modelsList Array with models
 */
function printNodeClusters($modelsList) {
	foreach ($modelsList as $parent => $models) {

		// Generate a cluster subgraph for each model path (app/models, plugin/*/models)
		print "\tsubgraph cluster$parent {\n";
		print "\t\tlabel=\"$parent\";\n";

		asort($models, SORT_STRING);
		foreach ($models as $modelName) {
			print "\t\t" . prepareNode($modelName, array());
		}
		print "\t}\n";
	}
}

/**
 * Print node relations in dot format
 *
 * @param array $relationData Array with relations data
 * @param array $relationSettings Array with relation settings
 */
function printNodeRelations($relationData, $relationSettings) {
	foreach ($relationData as $parent => $models) {

		foreach ($models as $modelName => $relations) {
			foreach ($relations as $type => $targets) {
				foreach ($targets as $targetModel) {
					print prepareRelation($modelName, $targetModel, $relationSettings[$type]);
				}
			}
		}
	}
}

// Gather data

$relationData = array();
$modelsList = array();
$modelsDirs = getModelsDirs();
foreach ($modelsDirs as $modelsDir) {
	$dir = new DirectoryIterator($modelsDir);

	// load AppModel or plugins' AppModel
	$parentDir = dirname($modelsDir);
	$parentDirRelative = basename($parentDir);
	if ($parentDirRelative == 'app') {
		if (file_exists($parentDir . '/app_model.php')) {
			require_once $parentDir . '/app_model.php';
		}
		else {
			class AppModel {
			}
		}
	}
	else {
		$pluginAppModel = $parentDir . '/' . $parentDirRelative . '_app_model.php';
		if (file_exists($pluginAppModel)) {
			require_once $pluginAppModel;
		}
	}

	foreach ($dir as $fileInfo) {
		if (! isValidModel($fileInfo)) {
			continue;
		}

		$modelFile = $fileInfo->getPathname();
		require_once $modelFile;
		$modelName = getModelName($fileInfo);

		// The fact that we had the file doesn't necessarily mean that we have a model defined in it
		if (! class_exists($modelName)) {
			continue;
		}

		$modelObject = new $modelName();
		$modelsList[$parentDirRelative][] = $modelName;

		foreach ($relationSettings as $relationName => $settings) {
			if (!empty($modelObject->$relationName) && is_array($modelObject->$relationName)) {
				$relationData[$parentDirRelative][$modelName][$relationName] = array_keys($modelObject->$relationName);
			}
		}
	}
}

// Print out the graph
printGraphHead();
printGraphLegend($relationSettings);
printNodeClusters($modelsList);
printNodeRelations($relationData, $relationSettings);
printGraphTail();

?>
