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
		$models['app'] = $this->getModels();

		/**
		 * Relations settings
		 */
		$relationsSettings = array(
			'belongsTo'           => array('label' => 'belongsTo', 'dir' => 'forward', 'color' => 'green'),
			'hasOne'              => array('label' => 'hasOne', 'dir' => 'forward', 'color' => 'magenta'),
			'hasMany'             => array('label' => 'hasMany', 'dir' => 'forward', 'color' => 'blue'),
			'hasAndBelongsToMany' => array('label' => 'HABTM', 'dir' => 'both', 'color' => 'red'),
		);
		$relationsData = $this->getRelations($models, $relationsSettings);

		$this->buildGraph($models, $relationsData, $relationsSettings);
		$this->outputGraph();
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
						$result[$plugin][$model][$relation] = array_keys($modelInstance->$relation);
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
	private function outputGraph($fileName = null, $format = 'png') {
		$result = 0;

		if (empty($fileName)) {
			$fileName = dirname(__FILE__) . DS . basename(__FILE__, '.php') . '.' . $format;
		}

		$imageData = $this->graph->fetch($format);
		$result = file_put_contents($fileName, $imageData);

		return $result;
	}

}	
?>
