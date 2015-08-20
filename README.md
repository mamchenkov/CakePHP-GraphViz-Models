CakePHP GraphViz Relations
-----------------------

This is a CakePHP shell that will find all models in your CakePHP application and
plugins, figure out the relationships between them, and will build a nice graph,
visualzing those relationships for you.

It supports CakePHP 2.x, and requires PHP 5.3.3 or greater.  But there
are numerous ways it can fail to work for you.  If it does fail, please let me
know and I'll try to fix it.


Intallation via Composer

```
require: {
	"mamchenkov/cakephp-graphviz-models": "dev-master"
}
```

Load plugin in `app/Config/bootstrap.php`

```php
CakePlugin::load('GraphVizRelations');
```


Requirements
------------

Since version 2.1 (Angry Blue Octopus On Steroids), this script relies on phpDocumentor/Graphviz
package, rather than directly on the command-line dot tool.
But you will need to install the Graphviz command line tool incl. dot.


Usage
-----

The simplest way to use this shell is just to run it via CakePHP console:

```
$ Console/cake GraphVizRelations.graph
```

This should generate a graph.png image in your current directory.  Please have a look.

If you need more control, there are two options that this shell understand from the
command line: filename and format.   You can use either the filename option like so:

```
$ Console/cake GraphVizRelations.graph /tmp/my_models.png
```

Or you can use both options together like so:

```
$ Console/cake GraphVizRelations.graph /tmp/my_models.svg svg
```

No special magic is done about the filename.  What You Give Is What You Get.  As for the
format, you can use anything that GraphViz supports and understands.

If you are still looking for more control, have a look inside the script.  There are
plenty of settings, options, parameters, and comments for you to make sense of it all. It
might be helpful to get familiar with GraphViz Dot Language, just to feel a tiny bit more
confident.

Enjoy!
