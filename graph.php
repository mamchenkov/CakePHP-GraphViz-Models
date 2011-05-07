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
 * @todo Add support for plugins
 * @todo Add support for earlier versions of CakePHP
 *
 * @package app
 * @subpackage Utils
 * @author Leonid Mamchenkov <leonid@mamchenkov.net>
 */
class GraphShell extends Shell {

	/**
	 * We'll use this to store the graph thingy
	 */
	private $graph;

	/**
	 * Main
	 */
	public function main() {

		/**
		 * Graph settings
		 *
		 * Consult the GraphViz documentation for more options
		 */
		$graphSettings = array(
				'label' => 'Model relationships (as of ' . date('Y-m-d H:i:s') . ')', 
				'labelloc' => 't',
			);
		$this->graph = new Image_GraphViz(true, $graphSettings, 'models');

		$models = array();
		$models = $this->getModels();

		/**
		 * Relations settings
		 *
		 * If you graph is too noisy, try commenting out some of the relationships here
		 */
		$relationsSettings = array(
			'belongsTo'           => array('label' => 'belongsTo', 'dir' => 'forward', 'color' => 'green'),
			'hasOne'              => array('label' => 'hasOne', 'dir' => 'forward', 'color' => 'magenta'),
			'hasMany'             => array('label' => 'hasMany', 'dir' => 'forward', 'color' => 'blue'),
			'hasAndBelongsToMany' => array('label' => 'HABTM', 'dir' => 'both', 'color' => 'red'),
		);
		$relationsData = $this->getRelations($models, $relationsSettings);

		$this->buildGraph($models, $relationsData, $relationsSettings);

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
		debug($result);

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

				$modelInstance = ClassRegistry::init($model);

				foreach ($relationsSettings as $relation => $settings) {
					if (!empty($modelInstance->$relation) && is_array($modelInstance->$relation)) {
						$result[$plugin][$model][$relation] = array();

						$relations = $modelInstance->$relation;
						foreach ($relations as $name => $value) {
							if (is_array($value) && !empty($value) && !empty($value['className'])) {
								$result[$plugin][$model][$relation][] = $value['className'];
							}
							else {
								$result[$plugin][$model][$relation][] = $name;
							}
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

		foreach ($modelsList as $plugin => $models) {
			foreach ($models as $model) {
				$this->graph->addNode($model, array('label' => $model, 'shape' => 'box'));
			}
		}

		foreach ($relationsList as $plugin => $relations) {
			foreach ($relations as $model => $relations) {
				foreach ($relations as $relation => $relatedModels) {

					$relationsSettings = $settings[$relation];

					foreach ($relatedModels as $relatedModel) {
						$this->graph->addEdge(array($model => $relatedModel), $relationsSettings);
					}
				}
			}
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
