<?php
function __autoload($class)
{
    $class = str_replace("pear2\Pyrus\Developer\CoverageAnalyzer", "", $class);
    include "phar://" . __FILE__ . str_replace("\\", "/", $class) . ".php";
}
Phar::webPhar("pear2coverage.phar.php");
echo "This phar is a web application, run within your web browser to use\n";
exit -1;
__HALT_COMPILER(); ?>
�                    Web/Controller.phpT  O�JT  ��l��         Web/View.php�>  O�J�>  &��`�         Web/Aggregator.php  O�J  W�	�         Web/Exception.phph   O�Jh   Or�V�         SourceFile.php�  O�J�  K薶         Aggregator.php8  O�J8  э�;�         Exception.phpd   O�Jd   s��U�      
   Sqlite.phpUv  O�JUv   ��         SourceFile/PerTest.php  O�J  �wĶ      	   index.php�   O�J�   �Z'E�      	   cover.css"  O�J"  ����      <?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer\Web {
class Controller {
    protected $view;
    protected $sqlite;
    protected $rooturl;

    function __construct(View $view, $rooturl)
    {
        $this->view = $view;
        $view->setController($this);
        $this->rooturl = $rooturl;
    }

    function route()
    {
        if (!isset($_SESSION['fullpath']) || isset($_GET['restart'])) {
            unset($_SESSION['fullpath']);
            if (isset($_GET['setdatabase'])) {
                if (file_exists($_GET['setdatabase'])) {
                    try {
                        $this->sqlite = new Aggregator($_GET['setdatabase']);
                        $_SESSION['fullpath'] = $_GET['setdatabase'];
                        return $this->view->TOC($this->sqlite);
                    } catch (\Exception $e) {
                        echo $e->getMessage() . '<br />';
                        // fall through
                    }
                }
            }
            return $this->getDatabase();
        } else {
            try {
                $this->sqlite = new Aggregator($_SESSION['fullpath']);
                if (isset($_GET['test'])) {
                    if ($_GET['test'] === 'TOC') {
                        return $this->view->testTOC($this->sqlite);
                    }
                    if (isset($_GET['file'])) {
                        return $this->view->fileCoverage($this->sqlite, $_GET['file'], $_GET['test']);
                    }
                    return $this->view->testTOC($this->sqlite, $_GET['test']);
                }
                if (isset($_GET['file'])) {
                    if (isset($_GET['line'])) {
                        return $this->view->fileLineTOC($this->sqlite, $_GET['file'], $_GET['line']);
                    }
                    return $this->view->fileCoverage($this->sqlite, $_GET['file']);
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . '<br \>';
            }
            return $this->view->TOC($this->sqlite);
        }
    }

    function getFileLink($file, $test = null, $line = null)
    {
        if ($line) {
            return $this->rooturl . '?file=' . urlencode($file) . '&line=' . $line;
        }
        if ($test) {
            return $this->rooturl . '?file=' . urlencode($file) . '&test=' . $test;
        }
        return $this->rooturl . '?file=' . urlencode($file);
    }

    function getTOCLink($test = false)
    {
        if ($test === true) {
            return $this->rooturl . '?test=TOC';
        }
        if ($test) {
            return $this->rooturl . '?test=' . urlencode($test);
        }
        return $this->rooturl;
    }

    function getLogoutLink()
    {
        return $this->rooturl . '?restart=1';
    }

    function getDatabase()
    {
        $this->sqlite = $this->view->getDatabase();
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer\Web {
use pear2\Pyrus\Developer\CoverageAnalyzer\SourceFile;
/**
 * Takes a source file and outputs HTML source highlighting showing the
 * number of hits on each line, highlights un-executed lines in red
 */
class View
{
    protected $savePath;
    protected $testPath;
    protected $sourcePath;
    protected $source;
    protected $controller;

    function getDatabase()
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot open output - this should never happen');
        }
        $output->startElement('html');
         $output->startElement('head');
          $output->writeElement('title', 'Enter a path to the database');
         $output->endElement();
         $output->startElement('body');
          $output->writeElement('h2', 'Please enter the path to a coverage database');
          $output->startElement('form');
           $output->writeAttribute('name', 'getdatabase');
           $output->writeAttribute('method', 'GET');
           $output->writeAttribute('action', $this->controller->getTOCLink());
           $output->startElement('input');
            $output->writeAttribute('size', '90');
            $output->writeAttribute('type', 'text');
            $output->writeAttribute('name', 'setdatabase');
           $output->endElement();
           $output->startElement('input');
            $output->writeAttribute('type', 'submit');
           $output->endElement();
          $output->endElement();
         $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function setController($controller)
    {
        $this->controller = $controller;
    }

    function logoutLink(\XMLWriter $output)
    {
        $output->startElement('h5');
         $output->startElement('a');
          $output->writeAttribute('href', $this->controller->getLogoutLink());
          $output->text('Current database: ' . $_SESSION['fullpath'] . '.  Click to start over');
         $output->endElement();
        $output->endElement();
    }

    function TOC($sqlite)
    {
        $coverage = $sqlite->retrieveProjectCoverage();
        $this->renderSummary($sqlite, $sqlite->retrievePaths(), false, $coverage[1], $coverage[0]);
    }

    function testTOC($sqlite, $test = null)
    {
        if ($test) {
            return $this->renderTestCoverage($sqlite, $test);
        }
        $this->renderTestSummary($sqlite);
    }

    function fileLineTOC($sqlite, $file, $line)
    {
        $source = new SourceFile($file, $sqlite, $sqlite->testpath, $sqlite->codepath);
        return $this->renderLineSummary($file, $line, $sqlite->testpath, $source->getLineLinks($line));
    }

    function fileCoverage($sqlite, $file, $test = null)
    {
        if ($test) {
            $source = new SourceFile\PerTest($file, $sqlite, $sqlite->testpath, $sqlite->codepath, $test);
        } else {
            $source = new SourceFile($file, $sqlite, $sqlite->testpath, $sqlite->codepath);
        }
        return $this->render($source, $test);
    }

    function mangleFile($path, $istest = false)
    {
        return $this->controller->getFileLink($path, $istest);
    }

    function mangleTestFile($path)
    {
        return $this->controller->getTOClink($path);
    }

    function getLineLink($name, $line)
    {
        return $this->controller->getFileLink($name, null, $line);
    }

    function renderLineSummary($name, $line, $testpath, $tests)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render ' . $name . ' line ' . $line . ', opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Tests covering line ' . $line . ' of ' . $name);
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Tests covering line ' . $line . ' of ' . $name);
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->mangleFile($name));
        $output->text('File ' . $name . ' code coverage');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($tests as $testfile) {
            $output->startElement('li');
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleTestFile($testfile));
            $output->text(str_replace($testpath . '/', '', $testfile));
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    /**
     * @param pear2\Pyrus\Developer\CodeCoverage\SourceFile $source
     * @param string $istest path to test file this is covering, or false for aggregate
     */
    function render(SourceFile $source, $istest = false)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render ' . $source->shortName() . ', opening XML failed');
        }
        $output->setIndent(false);
        $output->startElement('html');
        $output->text("\n ");
        $output->startElement('head');
        $output->text("\n  ");
        if ($istest) {
            $output->writeElement('title', 'Code Coverage for ' . $source->shortName() . ' in ' .
                                  str_replace($source->testpath() . DIRECTORY_SEPARATOR, '', $istest));
        } else {
            $output->writeElement('title', 'Code Coverage for ' . $source->shortName());
        }
        $output->text("\n  ");
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->text("\n  ");
        $output->endElement();
        $output->text("\n ");
        $output->startElement('body');
        $output->text("\n ");
        $this->logoutLink($output);
        if ($istest) {
            $output->writeElement('h2', 'Code Coverage for ' . $source->shortName() . ' in ' .
                                  str_replace($source->testpath() . DIRECTORY_SEPARATOR, '', $istest));
        } else {
            $output->writeElement('h2', 'Code Coverage for ' . $source->shortName());
        }
        $output->text("\n ");
        $output->writeElement('h3', 'Coverage: ' . $source->coveragePercentage() . '%');
        $info = $source->coverageInfo();
        $output->writeElement('h3', 'Total lines: ' . $info[1] . ', Covered lines: ' . $info[0]);
        $output->text("\n ");
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('pre');
        foreach ($source->source() as $num => $line) {
            $coverage = $source->coverage($num);

            $output->startElement('span');
            $output->writeAttribute('class', 'ln');
            $output->text(str_pad($num, 8, ' ', STR_PAD_LEFT));
            $output->endElement();

            if ($coverage === false) {
                $output->text(str_pad(': ', 13, ' ', STR_PAD_LEFT) . $line);
                continue;
            }

            $output->startElement('span');
            if ($coverage < 1) {
                $output->writeAttribute('class', 'nc');
                $output->text('           ');
            } else {
                $output->writeAttribute('class', 'cv');
                if (!$istest) {
                    $output->startElement('a');
                    $output->writeAttribute('href', $this->getLineLink($source->name(), $num));
                }
                $output->text(str_pad($coverage, 10, ' ', STR_PAD_LEFT) . ' ');
                if (!$istest) {
                    $output->endElement();
                }
            }

            $output->text(': ' .  $line);
            $output->endElement();
        }
        $output->endElement();
        $output->text("\n ");
        $output->endElement();
        $output->text("\n ");
        $output->endElement();
        $output->endDocument();
    }

    function renderSummary(Aggregator $agg, array $results, $istest = false, $total = 1, $covered = 1)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render test summary, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        if ($istest) {
            $output->writeElement('title', 'Code Coverage Summary [' . $istest . ']');
        } else {
            $output->writeElement('title', 'Code Coverage Summary');
        }
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        if ($istest) {
            $output->writeElement('h2', 'Code Coverage Files for test ' . $istest);
        } else {
            $output->writeElement('h2', 'Code Coverage Files');
            $output->writeElement('h3', 'Total lines: ' . $total . ', covered lines: ' . $covered);
            $percent = 0;
            if ($total > 0) {
                $percent = ($covered / $total) * 100;
            }
            $output->startElement('p');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text($percent . '% code coverage');
            $output->endElement();
        }
        $this->logoutLink($output);
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOCLink(true));
        $output->text('Code Coverage per PHPT test');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($results as $i => $name) {
            $output->flush();
            $source = new SourceFile($name, $agg, $agg->testpath, $agg->codepath);
            $output->startElement('li');
            $percent = $source->coveragePercentage();
            $output->startElement('span');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text(' Coverage: ' . str_pad($percent . '%', 4, ' ', STR_PAD_LEFT));
            $output->endElement();
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleFile($name, $istest));
            $output->text($source->shortName());
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function renderTestSummary(Aggregator $agg)
    {
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
                throw new Exception('Cannot render tests summary, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Test Summary');
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Tests Executed, click for code coverage summary');
        $output->startElement('p');
        $output->startElement('a');
        $output->writeAttribute('href', $this->controller->getTOClink());
        $output->text('Aggregate Code Coverage for all tests');
        $output->endElement();
        $output->endElement();
        $output->startElement('ul');
        foreach ($agg->retrieveTestPaths() as $test) {
            $output->startElement('li');
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleTestFile($test));
            $output->text(str_replace($agg->testpath . '/', '', $test));
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }

    function renderTestCoverage(Aggregator $agg, $test)
    {
        $reltest = str_replace($agg->testpath . '/', '', $test);
        $output = new \XMLWriter;
        if (!$output->openUri('php://output')) {
            throw new Exception('Cannot render test ' . $reltest . ' coverage, opening XML failed');
        }
        $output->setIndentString(' ');
        $output->setIndent(true);
        $output->startElement('html');
        $output->startElement('head');
        $output->writeElement('title', 'Code Coverage Summary for test ' . $reltest);
        $output->startElement('link');
        $output->writeAttribute('href', 'cover.css');
        $output->writeAttribute('rel', 'stylesheet');
        $output->writeAttribute('type', 'text/css');
        $output->endElement();
        $output->endElement();
        $output->startElement('body');
        $this->logoutLink($output);
        $output->writeElement('h2', 'Code Coverage Files for test ' . $reltest);
        $output->startElement('ul');
        $paths = $agg->retrievePathsForTest($test);
        foreach ($paths as $name) {
            $source = new SourceFile\PerTest($name, $agg, $agg->testpath, $agg->codepath, $test);
            $output->startElement('li');
            $percent = $source->coveragePercentage();
            $output->startElement('span');
            if ($percent < 50) {
                $output->writeAttribute('class', 'bad');
            } elseif ($percent < 75) {
                $output->writeAttribute('class', 'ok');
            } else {
                $output->writeAttribute('class', 'good');
            }
            $output->text(' Coverage: ' . str_pad($source->coveragePercentage() . '%', 4, ' ', STR_PAD_LEFT));
            $output->endElement();
            $output->startElement('a');
            $output->writeAttribute('href', $this->mangleFile($name, $test));
            $output->text($source->shortName());
            $output->endElement();
            $output->endElement();
        }
        $output->endElement();
        $output->endElement();
        $output->endDocument();
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer\Web {
use pear2\Pyrus\Developer\CoverageAnalyzer;
class Aggregator extends CoverageAnalyzer\Aggregator
{
    public $codepath;
    public $testpath;
    protected $sqlite;
    public $totallines = 0;
    public $totalcoveredlines = 0;

    /**
     * @var string $testpath Location of .phpt files
     * @var string $codepath Location of code whose coverage we are testing
     */
    function __construct($db = ':memory:')
    {
        $this->sqlite = new CoverageAnalyzer\Sqlite($db);
        $this->codepath = $this->sqlite->codepath;
        $this->testpath = $this->sqlite->testpath;
    }

    function retrieveLineLinks($file)
    {
        return $this->sqlite->retrieveLineLinks($file);
    }

    function retrievePaths()
    {
        return $this->sqlite->retrievePaths();
    }

    function retrievePathsForTest($test)
    {
        return $this->sqlite->retrievePathsForTest($test);
    }

    function retrieveTestPaths()
    {
        return $this->sqlite->retrieveTestPaths();
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        return $this->sqlite->coveragePercentage($sourcefile, $testfile);
    }

    function coverageInfo($path)
    {
        return $this->sqlite->retrievePathCoverage($path);
    }

    function coverageInfoByTest($path, $test)
    {
        return $this->sqlite->retrievePathCoverageByTest($path, $test);
    }

    function retrieveCoverage($path)
    {
        return $this->sqlite->retrieveCoverage($path);
    }

    function retrieveProjectCoverage()
    {
        return $this->sqlite->retrieveProjectCoverage();
    }

    function retrieveCoverageByTest($path, $test)
    {
        return $this->sqlite->retrieveCoverageByTest($path, $test);
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer\Web {
class Exception extends \Exception {}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer {
class SourceFile
{
    protected $source;
    protected $path;
    protected $sourcepath;
    protected $coverage;
    protected $aggregator;
    protected $testpath;
    protected $linelinks;

    function __construct($path, Aggregator $agg, $testpath, $sourcepath)
    {
        $this->source = file($path);
        $this->path = $path;
        $this->sourcepath = $sourcepath;

        array_unshift($this->source, '');
        unset($this->source[0]); // make source array indexed by line number

        $this->aggregator = $agg;
        $this->testpath = $testpath;
        $this->setCoverage();
    }

    function setCoverage()
    {
        $this->coverage = $this->aggregator->retrieveCoverage($this->path);
    }

    function aggregator()
    {
        return $this->aggregator;
    }

    function testpath()
    {
        return $this->testpath;
    }

    function render(AbstractSourceDecorator $decorator = null)
    {
        if ($decorator === null) {
            $decorator = new DefaultSourceDecorator('.');
        }
        return $decorator->render($this);
    }

    function coverage($line)
    {
        if (!isset($this->coverage[$line])) {
            return false;
        }
        return $this->coverage[$line];
    }

    function coveragePercentage()
    {
        return $this->aggregator->coveragePercentage($this->path);
    }

    function coverageInfo()
    {
        return $this->aggregator->coverageInfo($this->path);
    }

    function name()
    {
        return $this->path;
    }

    function shortName()
    {
        return str_replace($this->sourcepath . DIRECTORY_SEPARATOR, '', $this->path);
    }

    function source()
    {
        return $this->source;
    }

    function coveredLines()
    {
        $info = $this->aggregator->coverageInfo($this->path);
        return $info[0];
    }

    function getLineLinks($line)
    {
        if (!isset($this->linelinks)) {
            $this->linelinks = $this->aggregator->retrieveLineLinks($this->path);
        }
        if (isset($this->linelinks[$line])) {
            return $this->linelinks[$line];
        }
        return false;
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer {
class Aggregator
{
    protected $codepath;
    protected $testpath;
    protected $sqlite;
    public $totallines = 0;
    public $totalcoveredlines = 0;

    /**
     * @var string $testpath Location of .phpt files
     * @var string $codepath Location of code whose coverage we are testing
     */
    function __construct($testpath, $codepath, $db = ':memory:')
    {
        $newcodepath = realpath($codepath);
        if (!$newcodepath) {
            if (!strpos($codepath, '://') || !file_exists($codepath)) {
                // stream wrapper not found
                throw new Exception('Can not find code path ' . $codepath);
            }
        } else {
            $codepath = $newcodepath;
        }
        $this->sqlite = new Sqlite($db, $codepath, $testpath);
        $this->codepath = $codepath;
        $this->sqlite->begin();
        echo "Scanning for xdebug coverage files...";
        $files = $this->scan($testpath);
        echo "done\n";
        $infostring = '';
        echo "Parsing xdebug results\n";
        if (count($files)) {
            foreach ($files as $testid => $xdebugfile) {
                if (!file_exists(str_replace('.xdebug', '.phpt', $xdebugfile))) {
                    echo "\nWARNING: outdated .xdebug file $xdebugfile, delete this relic\n";
                    continue;
                }
                $id = $this->sqlite->addTest(str_replace('.xdebug', '.phpt', $xdebugfile));
                echo '(' . $testid . ' of ' . count($files) . ') ' . $xdebugfile;
                $this->retrieveXdebug($xdebugfile, $id);
                echo "done\n";
            }
            echo "done\n";
            $this->sqlite->updateTotalCoverage();
            $this->sqlite->commit();
        } else {
            echo "done (no modified xdebug files)\n";
        }
    }

    function retrieveLineLinks($file)
    {
        return $this->sqlite->retrieveLineLinks($file);
    }

    function retrievePaths()
    {
        return $this->sqlite->retrievePaths();
    }

    function retrievePathsForTest($test)
    {
        return $this->sqlite->retrievePathsForTest($test);
    }

    function retrieveTestPaths()
    {
        return $this->sqlite->retrieveTestPaths();
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        return $this->sqlite->coveragePercentage($sourcefile, $testfile);
    }

    function coverageInfo($path)
    {
        return $this->sqlite->retrievePathCoverage($path);
    }

    function coverageInfoByTest($path, $test)
    {
        return $this->sqlite->retrievePathCoverageByTest($path, $test);
    }

    function retrieveCoverage($path)
    {
        return $this->sqlite->retrieveCoverage($path);
    }

    function retrieveCoverageByTest($path, $test)
    {
        return $this->sqlite->retrieveCoverageByTest($path, $test);
    }

    function retrieveProjectCoverage()
    {
        return $this->sqlite->retrieveProjectCoverage();
    }

    function retrieveXdebug($path, $testid)
    {
        $source = '$xdebug = ' . file_get_contents($path) . ";\n";
        eval($source);
        $this->sqlite->addCoverage(str_replace('.xdebug', '.phpt', $path), $testid, $xdebug);
    }

    function scan($testpath)
    {
        $a = $testpath;
        $testpath = realpath($testpath);
        if (!$testpath) {
            throw new Exception('Unable to process path' . $a);
        }
        $testpath = str_replace('\\', '/', $testpath);
        $this->testpath = $testpath;

        // get a list of all xdebug files
        $xdebugs = array();
        foreach (new \RegexIterator(
                                    new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator($testpath,
                                                                        0|\RecursiveDirectoryIterator::SKIP_DOTS)),
                                    '/\.xdebug$/') as $file) {
            if (strpos((string) $file, '.svn')) {
                continue;
            }
            $xdebugs[] = realpath((string) $file);
        }
        echo count($xdebugs), ' total...';

        $modified = array();
        $unmodified = array();
        foreach ($xdebugs as $path) {
            if ($this->sqlite->unChangedXdebug($path)) {
                $unmodified[$path] = true;
                continue;
            }
            $modified[] = $path;
        }
        $xdebugs = $modified;
        sort($xdebugs);
        // index from 1
        array_unshift($xdebugs, '');
        unset($xdebugs[0]);
        $test = array_flip($xdebugs);
        foreach ($this->sqlite->retrieveTestPaths() as $path) {
            $xdebugpath = str_replace('.phpt', '.xdebug', $path);
            if (isset($test[$xdebugpath]) || isset($unmodified[$xdebugpath])) {
                continue;
            }
            // remove outdated tests
            echo "Removing results from $xdebugpath\n";
            $this->sqlite->removeOldTest($path);
        }
        return $xdebugs;
    }

    function render($toPath)
    {
        $decorator = new DefaultSourceDecorator($toPath, $this->testpath, $this->codepath);
        echo "Generating project coverage data...";
        $coverage = $this->sqlite->retrieveProjectCoverage();
        echo "done\n";
        $decorator->renderSummary($this, $this->retrievePaths(), $this->codepath, false, $coverage[1],
                                  $coverage[0]);
        $a = $this->codepath;
        echo "[Step 2 of 2] Rendering per-test coverage...";
        $decorator->renderTestCoverage($this, $this->testpath, $a);
        echo "done\n";
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer {
class Exception extends \Exception {}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer {
class Sqlite
{
    protected $db;
    protected $totallines = 0;
    protected $coveredlines = 0;
    protected $pathCovered = array();
    protected $pathTotal = array();
    public $codepath;
    public $testpath;

    function __construct($path = ':memory:', $codepath = null, $testpath = null)
    {
        $this->db = new \Sqlite3($path);

        $sql = 'SELECT version FROM analyzerversion';
        if (@$this->db->querySingle($sql) == '5.0.0') {
            $this->codepath = $this->db->querySingle('SELECT codepath FROM paths');
            $this->testpath = $this->db->querySingle('SELECT testpath FROM paths');
            return;
        }
        // restart the database
        echo "Upgrading database to version 5.0.0";
        if (!$codepath || !$testpath) {
            throw new Exception('Both codepath and testpath must be set in ' .
                                'order to initialize a coverage database');
        }
        $this->codepath = $codepath;
        $this->testpath = $testpath;
        $this->db->exec('DROP TABLE IF EXISTS coverage;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS coverage_nonsource;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS not_covered;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS files;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS tests;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS paths;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS coverage_per_file;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS line_info;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS all_lines;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS xdebugs;');
        echo ".";
        $this->db->exec('DROP TABLE IF EXISTS analyzerversion;');
        echo ".";
        $this->db->exec('VACUUM;');

        echo ".";
        $this->db->exec('BEGIN');

        $query = '
          CREATE TABLE coverage (
            files_id integer NOT NULL,
            tests_id integer NOT NULL,
            linenumber INTEGER NOT NULL,
            PRIMARY KEY (files_id, linenumber, tests_id)
          );

          CREATE TABLE all_lines (
            files_id integer NOT NULL,
            linenumber INTEGER NOT NULL,
            PRIMARY KEY (files_id, linenumber)
          );

          CREATE TABLE line_info (
            files_id integer NOT NULL,
            covered INTEGER NOT NULL,
            total INTEGER NOT NULL,
            PRIMARY KEY (files_id)
          );
          ';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          CREATE TABLE coverage_nonsource (
            files_id integer NOT NULL,
            tests_id integer NOT NULL,
            PRIMARY KEY (files_id, tests_id)
          );
          ';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          CREATE TABLE files (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            filepath TEXT(500) NOT NULL,
            filepathmd5 TEXT(32) NOT NULL,
            issource BOOL NOT NULL,
            UNIQUE (filepath)
          );
          CREATE INDEX files_issource on files (issource);
          ';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          CREATE TABLE xdebugs (
            xdebugpath TEXT(500) NOT NULL,
            xdebugpathmd5 TEXT(32) NOT NULL,
            PRIMARY KEY (xdebugpath)
          );';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          CREATE TABLE tests (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            testpath TEXT(500) NOT NULL,
            testpathmd5 TEXT(32) NOT NULL,
            UNIQUE (testpath)
          );';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          CREATE TABLE analyzerversion (
            version TEXT(5) NOT NULL
          );

          INSERT INTO analyzerversion VALUES("5.0.0");

          CREATE TABLE paths (
            codepath TEXT NOT NULL,
            testpath TEXT NOT NULL
          );';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }

        echo ".";
        $query = '
          INSERT INTO paths VALUES(
            "' . $this->db->escapeString($codepath) . '",
            "' . $this->db->escapeString($testpath). '");';
        $worked = $this->db->exec($query);
        if (!$worked) {
            @$this->db->exec('ROLLBACK');
            $error = $this->db->lastErrorMsg();
            throw new Exception('Unable to create Code Coverage SQLite3 database: ' . $error);
        }
        $this->db->exec('COMMIT');
        echo "done\n";
    }

    function retrieveLineLinks($file)
    {
        $id = $this->getFileId($file);
        $query = 'SELECT t.testpath, c.linenumber
            FROM
                coverage c, tests t
            WHERE
                c.files_id=' . $id . ' AND t.id=c.tests_id';
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve line links for ' . $file .
                                ' line #' . $line .  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $ret[$res['linenumber']][] = $res['testpath'];
        }
        return $ret;
    }

    function retrieveTestPaths()
    {
        $query = 'SELECT testpath from tests ORDER BY testpath';
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve test paths :' . $error);
        }
        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }
        return $ret;
    }

    function retrievePathsForTest($test, $all = 0)
    {
        $id = $this->getTestId($test);
        $ret = array();
        if ($all) {
            $query = 'SELECT DISTINCT filepath
                FROM coverage_nonsource c, files
                WHERE c.tests_id=' . $id . '
                    AND files.id=c.files_id
                GROUP BY c.files_id
                ORDER BY filepath';
            $result = $this->db->query($query);
            if (!$result) {
                $error = $this->db->lastErrorMsg();
                throw new Exception('Cannot retrieve file paths for test ' . $test . ':' . $error);
            }
            while ($res = $result->fetchArray(SQLITE3_NUM)) {
                $ret[] = $res[0];
            }
        }
        $query = 'SELECT DISTINCT filepath
            FROM coverage c, files
            WHERE c.tests_id=' . $id . '
                AND files.id=c.files_id
            GROUP BY c.files_id
            ORDER BY filepath';
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve file paths for test ' . $test . ':' . $error);
        }
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }
        return $ret;
    }

    function retrievePaths($all = 0)
    {
        if ($all) {
            $query = 'SELECT filepath from files ORDER BY filepath';
        } else {
            $query = 'SELECT filepath from files WHERE issource=1 ORDER BY filepath';
        }
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve file paths :' . $error);
        }
        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[] = $res[0];
        }
        return $ret;
    }

    function coveragePercentage($sourcefile, $testfile = null)
    {
        if ($testfile) {
            $coverage = $this->retrievePathCoverageByTest($sourcefile, $testfile);
        } else {
            $coverage = $this->retrievePathCoverage($sourcefile);
        }
        if ($coverage[1]) {
        	return round(($coverage[0] / $coverage[1]) * 100);
        }
        return 0;
    }

    function retrieveProjectCoverage()
    {
        if ($this->totallines) {
            return array($this->coveredlines, $this->totallines);
        }
        $query = 'SELECT covered, total, filepath FROM line_info, files
                WHERE files.id=line_info.files_id';
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->pathTotal[$res['filepath']] = $res['total'];
            $this->totallines += $res['total'];
            $this->pathCovered[$res['filepath']] = $res['covered'];
            $this->coveredlines += $res['covered'];
        }

        return array($this->coveredlines, $this->totallines);
    }

    function retrievePathCoverage($path)
    {
        if (!$this->totallines) {
            // set up the cache
            $this->retrieveProjectCoverage();
        }
        if (!isset($this->pathCovered[$path])) {
            return array(0, 0);
        }
        return array($this->pathCovered[$path], $this->pathTotal[$path]);
    }

    function retrievePathCoverageByTest($path, $test)
    {
        $id = $this->getFileId($path);
        $testid = $this->getTestId($test);

        $query = 'SELECT COUNT(linenumber)
            FROM all_lines
            WHERE files_id=' . $id;
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve path coverage for ' . $path .
                                ' in test ' . $test . ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $total = $res[0];
        }

        $query = 'SELECT COUNT(linenumber)
            FROM coverage
            WHERE files_id=' . $id. ' AND tests_id=' . $testid;
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve path coverage for ' . $path .
                                ' in test ' . $test . ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $covered = $res[0];
        }
        return array($covered, $total);
    }

    function retrieveCoverageByTest($path, $test)
    {
        $id = $this->getFileId($path);
        $testid = $this->getTestId($test);

        $query = 'SELECT 1 as coverage, linenumber FROM coverage
                    WHERE files_id=' . $id . ' AND tests_id=' . $testid;
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve test ' . $test .
                                ' coverage for ' . $path.  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $ret[$res['linenumber']] = $res['coverage'];
        }
        $query = 'SELECT linenumber
            FROM all_lines
            WHERE files_id=' . $id;
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve path coverage for ' . $path .
                                ' in test ' . $test . ': ' . $error);
        }

        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            if (isset($ret[$res['linenumber']])) {
                continue;
            }
            $ret[$res['linenumber']] = 0;
        }
        return $ret;
    }

    function getFileId($path)
    {
        $query = 'SELECT id FROM files WHERE filepath=:filepath';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':filepath', $path);
        if (!($result = $stmt->execute())) {
            throw new Exception('Unable to retrieve file ' . $path . ' id from database');
        }
        while ($id = $result->fetchArray(SQLITE3_NUM)) {
            return $id[0];
        }
        throw new Exception('Unable to retrieve file ' . $path . ' id from database');
    }

    function getTestId($path)
    {
        $query = 'SELECT id FROM tests WHERE testpath=:filepath';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':filepath', $path);
        if (!($result = $stmt->execute())) {
            throw new Exception('Unable to retrieve test file ' . $path . ' id from database');
        }
        while ($id = $result->fetchArray(SQLITE3_NUM)) {
            return $id[0];
        }
        throw new Exception('Unable to retrieve test file ' . $path . ' id from database');
    }

    function removeOldTest($testpath, $id = null)
    {
        if ($id === null) {
            $id = $this->getTestId($testpath);
        }
        echo "deleting old test ", $testpath,'.';
        $this->db->exec('DELETE FROM tests WHERE id=' . $id);
        echo '.';
        $this->db->exec('DELETE FROM coverage WHERE tests_id=' . $id);
        echo '.';
        $this->db->exec('DELETE FROM coverage_nonsource WHERE tests_id=' . $id);
        echo '.';
        $this->db->exec('DELETE FROM xdebugs WHERE xdebugpath="' .
                        $this->db->escapeString(str_replace('.phpt', '.xdebug', $testpath)) . '"');
        echo "done\n";
    }

    function addTest($testpath, $id = null)
    {
        try {
            $id = $this->getTestId($testpath);
            $this->db->exec('UPDATE tests SET testpathmd5="' . md5_file($testpath) . '" WHERE id=' . $id);
        } catch (Exception $e) {
            echo "Adding new test $testpath\n";
            $query = 'REPLACE INTO tests
                (testpath, testpathmd5)
                VALUES(:testpath, :md5)';
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':testpath', $testpath);
            $md5 = md5_file($testpath);
            $stmt->bindValue(':md5', $md5);
            $stmt->execute();
            $id = $this->db->lastInsertRowID();
        }

        $query = 'REPLACE INTO xdebugs
            (xdebugpath, xdebugpathmd5)
            VALUES(:testpath, :md5)';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':testpath', str_replace('.phpt', '.xdebug', $testpath));
        $md5 = md5_file(str_replace('.phpt', '.xdebug', $testpath));
        $stmt->bindValue(':md5', $md5);
        $stmt->execute();
        return $id;
    }

    function unChangedXdebug($path)
    {
        $query = 'SELECT xdebugpathmd5 FROM xdebugs
                    WHERE xdebugpath=:path';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':path', $path);
        $result = $stmt->execute();
        if (!$result) {
            return false;
        }
        $md5 = 0;
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $md5 = $res[0];
        }
        if (!$md5) {
            return false;
        }
        if ($md5 == md5_file($path)) {
            return true;
        }
        return false;
    }

    function getTotalCoverage($file, $linenumber)
    {
        $query = 'SELECT COUNT(linenumber) FROM coverage
                    WHERE files_id=' . $this->getFileId($file) . ' AND linenumber=' . $linenumber;
        $result = $this->db->query($query);
        if (!$result) {
            return false;
        }
        $coverage = 0;
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $coverage = $res[0];
        }
        return $coverage;
    }

    function retrieveCoverage($path)
    {
        $id = $this->getFileId($path);
        $links = $this->retrieveLineLinks($path);
        $links = array_map(function ($arr) {return count($arr);}, $links);

        $query = 'SELECT linenumber FROM all_lines
                    WHERE files_id=' . $this->getFileId($path);
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }
        $coverage = 0;
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            if (!isset($links[$res[0]])) {
                $links[$res[0]] = 0;
            }
        }
        return $links;
    }

    /**
     * This is used to get the coverage which is then inserted into our
     * intermediate coverage_per_file table to speed things up at rendering
     */
    function retrieveSlowCoverage($id)
    {
        $query = 'SELECT COUNT(*) as coverage, linenumber FROM coverage WHERE files_id=' . $id . '
                    GROUP BY linenumber UNION
                  SELECT 0 as coverage, linenumber FROM all_lines WHERE files_id=' . $id . '
                    GROUP BY linenumber
                  ORDER BY linenumber';
        $result = $this->db->query($query);
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $ret[$res['linenumber']] = $res['coverage'];
        }
        return $ret;
    }

    function updateTotalCoverage()
    {
        echo "Updating coverage per-file intermediate table\n";
        $query = 'SELECT COUNT(DISTINCT linenumber), files_id FROM coverage GROUP BY files_id';
        $result = $this->db->query($query);
        echo ".";
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }

        $ret = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[$res[1]]['covered'] = $res[0];
        }

        $query = 'SELECT COUNT(linenumber), files_id FROM all_lines GROUP BY files_id';
        $result = $this->db->query($query);
        echo ".";
        if (!$result) {
            $error = $this->db->lastErrorMsg();
            throw new Exception('Cannot retrieve coverage for ' . $path.  ': ' . $error);
        }

        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $ret[$res[1]]['total'] = $res[0];
        }
        echo ".";

        foreach ($ret as $id => $lineinfo) {
            if (!isset($lineinfo['covered'])) {
                // this file has no coverage any more (was deleted), remove it
                $this->db->exec('DELETE FROM all_lines WHERE files_id=' . $id);
                $this->db->exec('DELETE FROM files WHERE id=' . $id);
                continue;
            }
            $this->db->exec('REPLACE INTO line_info (files_id, covered, total)
                            VALUES(' . $id . ',' . $lineinfo['covered'] . ',' . $lineinfo['total'] . ')');
            echo ".";
        }
        
        echo "done\n";
    }

    function updateAllLines($id, $results)
    {
        $query = 'SELECT linenumber FROM all_lines WHERE files_id=' . $id . ' ORDER BY linenumber ASC';
        $result = $this->db->query($query);
        $lines = array();
        while ($res = $result->fetchArray(SQLITE3_NUM)) {
            $lines[] = $res[0];
        }
        $new = array_diff($results, $lines);
        $old = array_diff($lines, $results);
        if (count($new) || count($old)) {
            foreach ($new as $line) {
                if (!$line) {
                    continue; // line 0 does not exist, skip this (xdebug quirk)
                }
                $query = 'INSERT INTO all_lines (files_id, linenumber) VALUES (' . $id . ',' . $line . ')';
                $this->db->exec($query);
            }
            if (count($old)) {
                $query = 'DELETE FROM all_lines WHERE files_id=' . $id .
                    ' AND linenumber IN (' . implode(',', $old) . ')';
                $this->db->exec($query);
                $query = 'DELETE FROM coverage WHERE files_id=' . $id .
                    ' AND linenumber IN (' . implode(',', $old) . ')';
                $this->db->exec($query);
            }
        }
    }

    function addFile($filepath, $issource = 0, $results = array())
    {
        $query = 'SELECT id FROM files WHERE filepath=:filepath';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':filepath', $filepath);
        if (!($result = $stmt->execute())) {
            throw new Exception('Unable to add file ' . $filepath . ' to database');
        }
        while ($id = $result->fetchArray(SQLITE3_NUM)) {
            if ($issource) {
                $this->updateAllLines($id[0], $results);
            }
            $query = 'UPDATE files SET filepathmd5=:md5 WHERE filepath=:filepath';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':filepath', $filepath);
            $md5 = md5_file($filepath);
            $stmt->bindParam(':md5', $md5);
            if (!$stmt->execute()) {
                throw new Exception('Unable to update file ' . $filepath . ' md5 in database');
            }
            return $id[0];
        }
        $query = 'INSERT INTO files
            (filepath, filepathmd5, issource)
            VALUES(:testpath, :md5, :issource)';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':testpath', $filepath);
        $md5 = md5_file($filepath);
        $stmt->bindValue(':md5', $md5);
        $stmt->bindValue(':issource', $issource);
        if (!$stmt->execute()) {
            throw new Exception('Unable to add file ' . $filepath . ' to database');
        }
        $id = $this->db->lastInsertRowID();
        if ($issource) {
            $this->updateAllLines($id, $results);
        }
        return $id;
    }

    function addCoverage($testpath, $testid, $xdebug)
    {
        $query = 'DELETE FROM coverage WHERE tests_id=' . $testid . ';
                  DELETE FROM coverage_nonsource WHERE tests_id=' . $testid;
        $worked = $this->db->exec($query);
        foreach ($xdebug as $path => $results) {
            if (!file_exists($path)) {
                continue;
            }
            if (strpos($path, $this->codepath) !== 0) {
                $issource = 0;
            } else {
                if (strpos($path, $this->testpath) === 0) {
                    $issource = 0;
                } else {
                    $issource = 1;
                }
            }
            echo ".";
            $id = $this->addFile($path, $issource, array_keys($results));
            if (!$issource) {
                $query = 'REPLACE INTO coverage_nonsource
                    (files_id, tests_id)
                    VALUES(' . $id . ', ' . $testid . ')';
                $worked = $this->db->exec($query);
                if (!$worked) {
                    $error = $this->db->lastErrorMsg();
                    throw new Exception('Cannot add coverage for test ' . $testpath .
                                        ', covered file ' . $path . ': ' . $error);
                }
                continue;
            }
            foreach ($results as $line => $info) {
                if (!$line) {
                    continue; // line 0 does not exist, skip this (xdebug quirk)
                }
                if ($info < 0) {
                    continue;
                }
                $query = 'REPLACE INTO coverage
                    (files_id, linenumber, tests_id)
                    VALUES(' . $id . ', ' . $line . ', ' . $testid  . ')';

                $worked = $this->db->exec($query);
                if (!$worked) {
                    $error = $this->db->lastErrorMsg();
                    throw new Exception('Cannot add coverage for test ' . $testpath .
                                        ', covered file ' . $path . ': ' . $error);
                }
            }
        }
    }

    function begin()
    {
        $this->db->exec('PRAGMA synchronous=OFF'); // make inserts super fast
        $this->db->exec('BEGIN');
    }

    function commit()
    {
        $this->db->exec('COMMIT');
        $this->db->exec('PRAGMA synchronous=NORMAL'); // make inserts super fast
        $this->db->exec('VACUUM');
    }

    /**
     * Retrieve a list of .phpt tests that either have been modified,
     * or the files they access have been modified
     * @return array
     */
    function getModifiedTests()
    {
        // first scan for new .phpt files
        foreach (new \RegexIterator(
                                    new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator($this->testpath,
                                                                        0|\RecursiveDirectoryIterator::SKIP_DOTS)),
                                    '/\.phpt$/') as $file) {
            if (strpos((string) $file, '.svn')) {
                continue;
            }
            $tests[] = realpath((string) $file);
        }
        $newtests = array();
        foreach ($tests as $path) {
            if ($path == $this->db->querySingle('SELECT testpath FROM tests WHERE testpath="' .
                                       $this->db->escapeString($path) . '"')) {
                continue;
            }
            $newtests[] = $path;
        }

        $modifiedPaths = array();
        $modifiedTests = array();
        $paths = $this->retrievePaths(1);
        echo "Scanning ", count($paths), " source files";
        foreach ($paths as $path) {
            echo '.';
            $query = '
                SELECT id, filepathmd5, issource FROM files where filepath="' .
                $this->db->escapeString($path) . '"';
            $result = $this->db->query($query);
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!file_exists($path) || md5_file($path) == $res['filepathmd5']) {
                    if ($res['issource'] && !file_exists($path)) {
                        $this->db->exec('
                            DELETE FROM files WHERE id='. $res['id'] .';
                            DELETE FROM coverage WHERE files_id='. $res['id'] . ';
                            DELETE FROM all_lines WHERE files_id='. $res['id'] . ';
                            DELETE FROM line_info WHERE files_id='. $res['id'] . ';');
                    }
                    break;
                }
                $modifiedPaths[] = $path;
                // file is modified, get a list of tests that execute this file
                if ($res['issource']) {
                    $query = '
                        SELECT t.testpath
                        FROM coverage c, tests t
                        WHERE
                            c.files_id=' . $res['id'] . '
                            AND t.id=c.tests_id';
                    $result2 = $this->db->query($query);
                    while ($res = $result2->fetchArray(SQLITE3_NUM)) {
                        $modifiedTests[$res[0]] = true;
                    }
                } else {
                    $query = '
                        SELECT t.testpath
                        FROM coverage_nonsource c, tests t
                        WHERE
                            c.files_id=' . $res['id'] . '
                            AND t.id=c.tests_id';
                    $result2 = $this->db->query($query);
                    while ($res = $result2->fetchArray(SQLITE3_NUM)) {
                        $modifiedTests[$res[0]] = true;
                    }
                }
                break;
            }
        }
        echo "done\n";
        echo count($modifiedPaths), ' modified files resulting in ',
            count($modifiedTests), " modified tests\n";
        $paths = $this->retrieveTestPaths();
        echo "Scanning ", count($paths), " test paths";
        foreach ($paths as $path) {
            echo '.';
            $query = '
                SELECT id, testpathmd5 FROM tests where testpath="' .
                $this->db->escapeString($path) . '"';
            $result = $this->db->query($query);
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!file_exists($path)) {
                    $this->removeOldTest($path, $res['id']);
                    continue;
                }
                if (md5_file($path) != $res['testpathmd5']) {
                    $modifiedTests[$path] = true;
                }
            }
        }
        echo "done\n";
        echo count($newtests), ' new tests and ', count($modifiedTests), " modified tests should be re-run\n";
        return array_merge($newtests, array_keys($modifiedTests));
    }
}
}
?><?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer\SourceFile {
use pear2\Pyrus\Developer\CoverageAnalyzer\Aggregator,
    pear2\Pyrus\Developer\CoverageAnalyzer\AbstractSourceDecorator;
class PerTest extends \pear2\Pyrus\Developer\CoverageAnalyzer\SourceFile
{
    protected $testname;

    function __construct($path, Aggregator $agg, $testpath, $sourcepath, $testname)
    {
        $this->testname = $testname;
        parent::__construct($path, $agg, $testpath, $sourcepath);
    }

    function setCoverage()
    {
        $this->coverage = $this->aggregator->retrieveCoverageByTest($this->path, $this->testname);
    }

    function coveredLines()
    {
        $info = $this->aggregator->coverageInfoByTest($this->path, $this->testname);
        return $info[0];
    }

    function render(AbstractSourceDecorator $decorator = null)
    {
        if ($decorator === null) {
            $decorator = new DefaultSourceDecorator('.');
        }
        return $decorator->render($this, $this->testname);
    }

    function coveragePercentage()
    {
        return $this->aggregator->coveragePercentage($this->path, $this->testname);
    }

    function coverageInfo()
    {
        return $this->aggregator->coverageInfoByTest($this->path, $this->testname);
    }
}
}
?>
<?php
namespace pear2\Pyrus\Developer\CoverageAnalyzer {
session_start();
$view = new Web\View;
$rooturl = parse_url($_SERVER["REQUEST_URI"]);
$rooturl = $rooturl["path"];
$controller = new Web\Controller($view, $rooturl);
$controller->route();
}.ln {background-color:yellow;}
.cv {background-color:#CAD7FE;}
.nc {background-color:red;}
.bad {background-color:red;white-space:pre;font-family:courier;}
.ok {background-color:yellow;white-space:pre;font-family:courier;}
.good {background-color:green;white-space:pre;font-family:courier;}�h���y@a�����*�   GBMB