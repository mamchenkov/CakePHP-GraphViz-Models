<?php
/**
 * PHP 5
 *
 * @package app
 * @subpackage app.vendors.shells
 */

/**
 * Requre Image_GraphViz class from the PEAR package
 */
require_once 'Image/GraphViz.php';

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
 * $ php -f cake/console/cake.php graph [filename] [format]
 * </code>
 *
 * <b>Parameters:</b>
 *
 * * filename - an optional full path to the output file. If omitted, graph.png in
 *              current folder will be used
 * * format - an optional output format, supported by GraphViz (png,svg,etc)
 *
 * @todo Add support for earlier versions of CakePHP
 *
 * @package app
 * @subpackage Utils
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 * @version 2.0 (Blue Octopus On Steroids)
 */
class GraphShell extends Shell {

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
			'concentrate' => 'true',  // join multiple connecting lines between same nodes
			'landscape' => 'false',   // rotate resulting graph by 90 degrees
			'rankdir' => 'TB',        // interpret nodes from Top-to-Bottom or Left-to-Right (use: LR)
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
			'belongsTo'           => array('label' => 'belongsTo', 'dir' => 'both', 'color' => 'blue',    'arrowhead' => 'none', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasMany'             => array('label' => 'hasMany',   'dir' => 'both', 'color' => 'blue',    'arrowhead' => 'crow', 'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasOne'              => array('label' => 'hasOne',    'dir' => 'both', 'color' => 'magenta', 'arrowhead' => 'tee',  'arrowtail' => 'none', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
			'hasAndBelongsToMany' => array('label' => 'HABTM',     'dir' => 'both', 'color' => 'red',     'arrowhead' => 'crow', 'arrowtail' => 'crow', 'fontname' => 'Helvetica', 'fontsize' => 10, ),
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
	private $graph;

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
		$this->graph = new Image_GraphViz(true, $graphSettings, 'models');

		$models = $this->getModels();
		$relationsData = $this->getRelations($models, $this->relationsSettings);
		$this->buildGraph($models, $relationsData, $this->relationsSettings);

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
		$this->outputGraph($fileName, $format);
	}

	/**
	 * Get a list of all models to process
	 *
	 * Thanks to Harsha M V and Peter Martin via
	 * http://variable3.com/blog/2010/05/list-all-the-models-and-plugins-of-a-cakephp-application/
	 *
	 * @return array
	 */
	private function getModels() {
		$result = array();

		$result['app'] = App::objects('model');

		$plugins = App::objects('plugin');
		if (!empty($plugins)) {
			foreach ($plugins as $plugin) {
				$pluginModels = App::objects('model', App::pluginPath($plugin) . 'models' . DS, false);
				if (!empty($pluginModels)) {
					if (empty($result[$plugin])) {
						$result[$plugin] = array();
					}

					foreach ($pluginModels as $model) {
						$result[$plugin][] = "$plugin.$model";
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
	private function getRelations($modelsList, $relationsSettings) {
		$result = array();

		foreach ($modelsList as $plugin => $models) {
			foreach ($models as $model) {

				// This will work only if you have models and nothing else
				// in app/models/ and app/plugins/*/models/ . Otherwise, ***KABOOM*** and ***CRASH***.
				// Rearrange your files or patch up $this->getModels()
				$modelInstance = ClassRegistry::init($model);

				foreach ($relationsSettings as $relation => $settings) {
					if (!empty($modelInstance->$relation) && is_array($modelInstance->$relation)) {

						if ($this->miscSettings['realModels']) {
							$result[$plugin][$model][$relation] = array();
							foreach ($modelInstance->$relation as $name => $value) {
								if (is_array($value) && !empty($value) && !empty($value['className'])) {
									$result[$plugin][$model][$relation][] = $value['className'];
								}
								else {
									$result[$plugin][$model][$relation][] = $name;
								}
							}
						}
						else {
							$result[$plugin][$model][$relation] = array_keys($modelInstance->$relation);
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Populate graph with nodes and edges
	 *
	 * @param array $models Available models
	 * @param array $relations Availalbe relationships
	 * @param array $settings Settings
	 * @return void
	 */
	private function buildGraph($modelsList, $relationsList, $settings) {

		// We'll collect apps and plugins in here
		$plugins = array();

		// Add special cluster for Legend
		$plugins[] = self::GRAPH_LEGEND;
		$this->buildGraphLegend($settings);


		// Add nodes for all models
		foreach ($modelsList as $plugin => $models) {
			if (!in_array($plugin, $plugins)) {
				$plugins[] = $plugin;
			}

			foreach ($models as $model) {
				$label = preg_replace("/^$plugin\./", '', $model);
				$this->graph->addNode($model, array('label' => $label, 'shape' => 'box', 'fontname' => 'Helvetica', 'fontsize' => 10, ), $plugin);
			}
		}

		// Add all relations
		foreach ($relationsList as $plugin => $relations) {
			if (!in_array($plugin, $plugins)) {
				$plugins[] = $plugin;
			}

			foreach ($relations as $model => $relations) {
				foreach ($relations as $relation => $relatedModels) {

					$relationsSettings = $settings[$relation];
					$relationsSettings['label'] = ''; // no need to pollute the graph with too many labels

					foreach ($relatedModels as $relatedModel) {
						$this->graph->addEdge(array($model => $relatedModel), $relationsSettings);
					}
				}
			}
		}

		// Add clusters for apps and plugins
		foreach ($plugins as $plugin) {
			$this->graph->addCluster($plugin, $plugin);
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
	private function buildGraphLegend($relationsSettings) {

		$legendNodeSettings = array(
				'shape' => 'box',
				'width' => 0.5,
				'fontname' => 'Helvetica', 
				'fontsize' => 10, 
			);

		foreach ($relationsSettings as $relation => $relationSettings) {
			$from = $relation . '_from';
			$to = $relation . '_to';

			$legendNodeSettings['label'] = 'A';
			$this->graph->addNode($from, $legendNodeSettings, self::GRAPH_LEGEND);

			$legendNodeSettings['label'] = 'B';
			$this->graph->addNode($to, $legendNodeSettings, self::GRAPH_LEGEND);

			$this->graph->addEdge(array($from => $to), $relationSettings);
		}
	}

	/**
	 * Save graph to a file
	 *
	 * @param string $fileName File to save graph to (full path)
	 * @param string $format Any of the GraphViz supported formats
	 * @return numeric Number of bytes written to file
	 */
	private function outputGraph($fileName = null, $format = null) {
		$result = 0;

		// Fall back on PNG if no format was given
		if (empty($format)) {
			$format = 'png';
		}

		// Fall back on something when nothing is given
		if (empty($fileName)) {
			$fileName = basename(__FILE__, '.php') . '.' . $format;
		}

		$imageData = $this->graph->fetch($format);
		$result = file_put_contents($fileName, $imageData);

		return $result;
	}

}	
?>
