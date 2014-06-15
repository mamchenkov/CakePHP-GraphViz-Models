<?php
/**
 * PHP 5
 *
 * @package app
 * @subpackage app.vendors.shells
 */
App::uses('AppShell', 'Console\Command');
App::uses('CakeLog', 'Log');
App::uses('ClassRegistry', 'Utility');

use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;

/**
 * CakePHP GraphViz Models
 *
 * This shell examines all models in the current application and its plugins,
 * finds all relations between them, and then generates a graphical representation
 * of those.  The graph is built using an excellent GraphViz tool.
 *
 * <b>Usage:</b>
 *
 * <code>
 * $ Console/cake graph [filename] [format]
 * </code>
 *
 * <b>Parameters:</b>
 *
 * * filename - an optional full path to the output file. If omitted, graph.png in
 *              current folder will be used
 * * format - an optional output format, supported by GraphViz (png,svg,etc)
 *
 * @package app
 * @subpackage Utils
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 * @version 2.1 (Angry Blue Octopus On Steroids)
 */
class GraphShell extends AppShell {

/**
 * Graph settings
 *
 * Consult the GraphViz documentation for node, edge, and
 * graph attributes for more information.
 *
 * @link http://www.graphviz.org/doc/info/attrs.html
 */
	public $graphSettings = array(
			'label' => 'CakePHP Model Relationships',
			'labelloc' => 't',
			'fontname' => 'Helvetica',
			'fontsize' => 12,
			//
			// Tweaking these might produce better results
			//
			'concentrate' => 'true', // join multiple connecting lines between same nodes
			'landscape' => 'false', // rotate resulting graph by 90 degrees
			'rankdir' => 'TB', // interpret nodes from Top-to-Bottom or Left-to-Right (use: LR)
		);

/**
 * Relations settings
 *
 * My weak attempt at using Crow's Foot Notation for
 * CakePHP model relationships.
 *
 * NOTE: Order of the relations in this list is sometimes important.
 */
	public $relationsSettings = array(
			'belongsTo' => array('label' => 'belongsTo', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'none', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasMany' => array('label' => 'hasMany', 'dir' => 'both', 'color' => 'blue', 'arrowhead' => 'crow', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasOne' => array('label' => 'hasOne', 'dir' => 'both', 'color' => 'magenta', 'arrowhead' => 'tee', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasAndBelongsToMany' => array('label' => 'HABTM', 'dir' => 'both', 'color' => 'red', 'arrowhead' => 'crow', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
		);

/**
 * Miscelanous settings
 *
 * These are settings that change the behavior
 * of the application, but which I didn't feel
 * safe enough to send to GraphViz.
 */
	public $miscSettings = array(
		// If true, graphs will use only real model names (via className).  If false,
		// graphs will use whatever you specified as the name of relationship class.
		// This might get very confusing, so you mostly would want to keep this as true.
		'realModels' => true,

		// If set to not empty value, the value will be used as a date() format, that
		// will be appended to the main graph label. Set to empty string or null to avoid
		// timestamping generated graphs.
		'timestamp' => ' [Y-m-d H:i:s]',
	);

/**
 * Change this to something else if you
 * have a plugin with the same name.
 */
	const GRAPH_LEGEND = 'Graph Legend';

/**
 * We'll use this to store the graph thingy
 */
	public $graph;

/**
 * CakePHP's Shell main() routine
 *
 * This routine is called when the shell is executed via console.
 */
	public function main() {
		// Prepare graph settings
		$graphSettings = $this->graphSettings;
		if (!empty($this->miscSettings['timestamp'])) {
			$graphSettings['label'] .= date($this->miscSettings['timestamp']);
		}

		// Initialize the graph
		$this->graph = new Graph('models');
		foreach ($graphSettings as $key => $value) {
			call_user_func(array($this->graph, 'set' . $key), $value);
		}

		$models = $this->_getModels();
		$relationsData = $this->_getRelations($models, $this->relationsSettings);
		$this->_buildGraph($models, $relationsData, $this->relationsSettings);

		// See if file name and format were given
		$fileName = null;
		$format = null;
		if (!empty($this->args[0])) {
			$fileName = $this->args[0];
		}
		if (!empty($this->args[1])) {
			$format = $this->args[1];
		}

		// Save graph image
		$this->_outputGraph($fileName, $format);
	}

/**
 * Return a list of instantiable classes from a list of classes
 *
 * @param array $classList
 * @return array $classList
 */
	protected function _onlyInstantiableClasses($classList, $plugin = null) {
		// make sure the model isn't abstract or an interface
		$toDelete = [];
		foreach ($classList as $key => $class) {
			App::uses($class, (($plugin != null) ? $plugin . '.' : '') . 'Model');
			$reflectionClass = new ReflectionClass($class);
			if (!$reflectionClass->isInstantiable()) {
				$toDelete[] = $key;
			}
		}
		foreach ($toDelete as $value) {
			unset($classList[$value]);
		}

		return $classList;
	}

/**
 * Get a list of all models to process
 *
 * This will only include models that can be instantiated, and plugins that are loaded by the bootstrap
 *
 * @return array
 */
	protected function _getModels() {
		$result = array();

		$result['app'] = $this->_onlyInstantiableClasses(App::objects('Model', null, false));
		$plugins = CakePlugin::loaded();
		if (!empty($plugins)) {
			foreach ($plugins as $plugin) {
				$pluginModels = $this->_onlyInstantiableClasses(App::objects($plugin . '.Model', null, false), $plugin);
				if (!empty($pluginModels)) {
					if (!isset($result[$plugin])) {
						$result[$plugin] = array();
					}

					foreach ($pluginModels as $model) {
						$result[$plugin][] = $plugin . '.' . $model;
					}
				}
			}
		}

		return $result;
	}

/**
 * Get the list of relationss for given models
 *
 * @param array $modelsList List of models by module (apps, plugins, etc)
 * @param array $relationsSettings Relationship settings
 * @return array
 */
	protected function _getRelations($modelsList, $relationsSettings) {
		$result = array();

		foreach ($modelsList as $plugin => $models) {
			foreach ($models as $model) {

				// This will work only if you have models and nothing else
				// in app/Model/ and app/Plugins/*/Model/ . Otherwise, ***KABOOM*** and ***CRASH***.
				// Rearrange your files or patch up $this->getModels()
				$modelInstance = ClassRegistry::init($model);

				foreach ($relationsSettings as $relation => $settings) {
					if (!empty($modelInstance->$relation) && is_array($modelInstance->$relation)) {

						if ($this->miscSettings['realModels']) {
							$result[$plugin][$model][$relation] = array();
							foreach ($modelInstance->$relation as $name => $value) {
								if (is_array($value) && !empty($value) && !empty($value['className'])) {
									$result[$plugin][$model][$relation][] = $value['className'];
								} else {
									$result[$plugin][$model][$relation][] = $name;
								}
							}
						} else {
							$result[$plugin][$model][$relation] = array_keys($modelInstance->$relation);
						}
					}
				}
			}
		}

		return $result;
	}

/**
 * Add a cluster to a graph
 *
 * If the cluster already exists on the graph, then the cluster graph is returned
 *
 * @param Graph $graph
 * @param string $name
 * @param string $label
 * @return Graph $clusterGraph
 */
	protected function _addCluster($graph, $name, $label = null, $attributes = array()) {
		if ($label == null) {
			$label = $name;
		}
		if (!$graph->hasGraph('cluster_' . $name)) {
			$clusterGraph = Graph::create('cluster_' . $name);
			$this->_addAttributes($clusterGraph, $attributes);
			$this->graph->addGraph($clusterGraph->setLabel($label));
		} else {
			$clusterGraph = $this->graph->getGraph('cluster_' . $name);
		}
		return $clusterGraph;
	}

/**
 * Set attributes on an object
 *
 * @param mixed $object
 * @param array $attributes
 * @return mixed $object
 */
	protected function _addAttributes($object, $attributes) {
		foreach ($attributes as $key => $value) {
			call_user_func(array($object, 'set' . $key), $value);
		}
		return $object;
	}

/**
 * Populate graph with nodes and edges
 *
 * @param array $models Available models
 * @param array $relations Availalbe relationships
 * @param array $settings Settings
 * @return void
 */
	protected function _buildGraph($modelsList, $relationsList, $settings) {
		// We'll collect apps and plugins in here
		$plugins = array();

		// Add special cluster for Legend
		$plugins[] = self::GRAPH_LEGEND;
		$this->_buildGraphLegend($settings);

		// Add nodes for all models
		foreach ($modelsList as $plugin => $models) {
			if (!in_array($plugin, $plugins)) {
				$plugins[] = $plugin;
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
			}

			foreach ($models as $model) {
				$label = preg_replace("/^$plugin\./", '', $model);
				$node = Node::create($model, $label)->setShape('box')->setFontname('Helvetica')->setFontsize(10);
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
				$pluginGraph->setNode($node);
			}
		}

		// Add all relations
		foreach ($relationsList as $plugin => $relations) {
			if (!in_array($plugin, $plugins)) {
				$plugins[] = $plugin;
				$pluginGraph = $this->_addCluster($this->graph, $plugin);
			}

			foreach ($relations as $model => $relations) {
				foreach ($relations as $relation => $relatedModels) {

					$relationsSettings = $settings[$relation];
					$relationsSettings['label'] = ''; // no need to pollute the graph with too many labels

					foreach ($relatedModels as $relatedModel) {
						$modelNode = $this->graph->findNode($model);
						if ($modelNode == null) {
							CakeLog::error('Could not find node for ' . $model);
						} else {
							$relatedModelNode = $this->graph->findNode($relatedModel);
							if ($relatedModelNode == null) {
								CakeLog::error('Could not find node for ' . $relatedModel);
							} else {
								$edge = Edge::create($modelNode, $relatedModelNode);
								$this->graph->link($edge);
								$this->_addAttributes($edge, $relationsSettings);
							}
						}
					}
				}
			}
		}
	}

/**
 * Add graph legend
 *
 * For every type of the relationship in CakePHP we add two nodes (from, to)
 * to the graph and then link them, using the settings of each relationship
 * type.  Nodes are grouped into the Graph Legend cluster, so they don't
 * interfere with the rest of the nodes.
 *
 * @param array $relationsSettings Array with relation types and settings
 * @return void
 */
	protected function _buildGraphLegend($relationsSettings) {
		$legendNodeSettings = array(
				'shape' => 'box',
				'width' => 0.5,
				'fontname' => 'Helvetica',
				'fontsize' => 10,
			);

		$legend = $this->_addCluster($this->graph, self::GRAPH_LEGEND);

		foreach ($relationsSettings as $relation => $relationSettings) {
			$from = $relation . '_from';
			$to = $relation . '_to';

			$fromNode = Node::create($from, 'A');
			$this->_addAttributes($fromNode, $legendNodeSettings);
			$legend->setNode($fromNode);

			$toNode = Node::create($to, 'B');
			$this->_addAttributes($toNode, $legendNodeSettings);
			$legend->setNode($toNode);

			$edge = Edge::create($fromNode, $toNode);
			$this->_addAttributes($edge, $relationSettings);
			$legend->link($edge);
		}
	}

/**
 * Save graph to a file
 *
 * @param string $fileName File to save graph to (full path)
 * @param string $format Any of the GraphViz supported formats
 * @return numeric Number of bytes written to file
 */
	protected function _outputGraph($fileName = null, $format = null) {
		$result = 0;

		// Fall back on PNG if no format was given
		if (empty($format)) {
			$format = 'png';
		}

		// Fall back on something when nothing is given
		if (empty($fileName)) {
			$fileName = 'graph.' . $format;
		}

		$this->graph->export($format, $fileName);

		return true;
	}
}
