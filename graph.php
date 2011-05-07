<?php
/**
 * PHP 5
 *
 * @package app
 * @subpackage app.vendors.shells
 */
/**
 * CakePHP GraphViz Models
 *
 * This shell examines all models in the current application and its plugins,
 * finds all relations between them, and then generates a graphical representation
 * of those.  The graph is built using an excellent GraphViz tool.
 *
 * @package app
 * @subpackage Utils
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 */
class GraphShell extends Shell {

	/**
	 * Graph settings for each type of the relationship.
	 *
	 * Consult GraphViz documentation for dot language if
	 * you need more information.
	 */
	private $relationsSettings = array(
			'belongsTo'           => array('dir' => 'forward', 'color' => 'green'),
			'hasOne'              => array('dir' => 'forward', 'color' => 'magenta'),
			'hasMany'             => array('dir' => 'forward', 'color' => 'blue'),
			'hasAndBelongsToMany' => array('dir' => 'both', 'color' => 'red'),
		);

	/**
	 * Main
	 */
	public function main() {

		$relationsData = array();
		$models = array();
		$models['app'] = $this->getModels();

		foreach ($models['app'] as $model) {
			if ($model == 'BaseWorkflow') {
				continue;
			}

			$modelInstance = ClassRegistry::init($model);

			foreach ($this->relationsSettings as $relation => $settings) {
				if (!empty($modelInstance->$relation) && is_array($modelInstance->$relation)) {
					$relationsData['app'][$model][$relation] = array_keys($modelInstance->$relation);
				}
			}
		}

		// Let's build the graph string now
		$graph = '';
		$graph .= $this->buildGraphHead();
		$graph .= $this->buildGraphLegend($this->relationsSettings);
		$graph .= $this->buildNodeClusters($models);
		$graph .= $this->buildNodeRelations($relationsData, $this->relationsSettings);
		$graph .= $this->buildGraphTail();

		$this->out($graph);
	}

	/**
	 * Get a list of all models to process
	 *
	 * Thanks to Harsha M V and Peter Martin via
	 * http://variable3.com/blog/2010/05/list-all-the-models-and-plugins-of-a-cakephp-application/
	 *
	 * @todo Add support for plugins
	 * @todo Add support for earlier versions of CakePHP
	 *
	 * @return array
	 */
	private function getModels() {
		$result = array();

		$result = App::objects('model');

		return $result;
	}

	/**
	 * Generate the graph header
	 *
	 * Here we start building the graph string in dot language
	 *
	 * @return string
	 */
	private function buildGraphHead() {
		$result = '';

		$result .= "digraph models {\n";
		$result .= "\tlabel=\"Model relationships (Date: " . date('Y-m-d H:i:s') . ")\";\n";
		$result .= "\tlabelloc=\"t\";\n";
		//$result .= "\trankdir=\"LR\";\n";
		$result .= "\tnode [shape=\"box\"];\n";

		return $result;
	}

	/**
	 * Generate graph legend
	 *
	 * Here we build the legend, describing the colors we use for
	 * different relationships and such
	 *
	 * @param array $settings Relationship settings
	 * @return string
	 */
	private function buildGraphLegend($settings) {
		$result = '';

		$result .= "\tsubgraph clusterLegend {\n";
		$result .= "\t\tlabel=\"Legend\";\n";
		$result .= "\t\tstyle=\"filled\";\n";

		$second = '';
		foreach ($settings as $type => $options) {
			$options['label'] = $type;
			list($first, $second) = $this->getTargets($second);
			$result .= "\t" . $this->prepareRelation($first, $second, $options);
		}
		$result .= "\t}\n";

		return $result;
	}

	/**
	 * Generate clusters of nodes
	 *
	 * Here we group models together so that they would end up
	 * either in the 'app' cluster or in their approrpriate 
	 * plugin cluster.
	 *
	 * @param array $models List of models by plugin
	 * @return string
	 */
	private function buildNodeClusters($models) {
		$result = '';

		foreach ($models as $parent => $list) {

			// Generate a cluster subgraph for each model path (app/models, plugin/*/models)
			$result .= "\tsubgraph cluster$parent {\n";
			$result .= "\t\tlabel=\"$parent\";\n";

			asort($list, SORT_STRING);
			foreach ($list as $modelName) {
				$result .= "\t\t" . $this->prepareNode($modelName, array());
			}
			$result .= "\t}\n";
		}

		return $result;
	}

	/**
	 * Generate the relations part of the graph
	 *
	 * This is what links all models on the graph to each other
	 *
	 * @param array $relationData Relationships data
	 * @param array $relationSettings Settings to use for relations
	 * @return string
	 */
	private function buildNodeRelations($relationData, $relationSettings) {
		$result = '';

		foreach ($relationData as $parent => $models) {

			foreach ($models as $modelName => $relations) {
				foreach ($relations as $type => $targets) {
					foreach ($targets as $targetModel) {
						$result .= $this->prepareRelation($modelName, $targetModel, $relationSettings[$type]);
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Generate the end of the graph
	 *
	 * Close everything that we opened in the head or anywhere else along
	 * the way.  Shouldn't be much.
	 *
	 * @return string
	 */
	private function buildGraphTail() {
		$result = '';

		$result .= "}\n";

		return $result;
	}

	/**
	 * Get two characters to use as targets in the legend
	 *
	 * @param string $last Last-used character
	 * @return array
	 */
	private function getTargets($last = null) {
		if (! $last) {
			$last = '@'; // Next ASCII table character is A
		}
		$first = chr(ord($last) + 1);
		$second = chr(ord($last) + 2);

		return array($first, $second);
	}

	/**
	 * Generate a relation string in dot format
	 * 
	 * @param string $from Source node 
	 * @param string $to Destination node 
	 * @param array $settings Array of edge settings
	 */
	private function prepareRelation($from, $to, $settings) {
		$result = '';

		$settingsString = $this->prepareSettings($settings);
		$result = sprintf("\t%s -> %s %s;\n", $from, $to, $settingsString);

		return $result;
	}

	/**
	 * Convert an array of settings into dot string
	 *
	 * @param array $settings Associative array of settings
	 * @return string
	 */
	private function prepareSettings($settings) {
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
	 * Generate a node string in dot format
	 *
	 * @param string $node Node name
	 * @param array $settings Array of node settings
	 * @return string
	 */
	private function prepareNode($node, $settings) {
		$result = '';

		$settingsString = $this->prepareSettings($settings);
		$result = sprintf("\t%s %s;\n", $node, $settingsString);

		return $result;
	}

}	
?>
