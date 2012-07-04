<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Routing\PartialMatcherTest.
 */

namespace Drupal\system\Tests\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use Drupal\simpletest\UnitTestBase;
use Drupal\Core\Routing\PathMatcher;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing\MatcherDumper;

/**
 * Basic tests for the UrlMatcherDumper.
 */
class PathMatcherTest extends UnitTestBase {

  /**
   * A collection of shared fixture data for tests.
   *
   * @var RoutingFixtures
   */
  protected $fixtures;

  public static function getInfo() {
    return array(
      'name' => 'Path matcher tests',
      'description' => 'Confirm that the path matching library is working correctly.',
      'group' => 'Routing',
    );
  }

  function __construct($test_id = NULL) {
    parent::__construct($test_id);

    $this->fixtures = new RoutingFixtures();
  }

  public function tearDown() {
    $this->fixtures->dropTables(Database::getConnection());

   parent::tearDown();
  }

  /**
   * Confirms that the correct candidate outlines are generated.
   */
  public function testCandidateOutlines() {

    $connection = Database::getConnection();
    $matcher = new PathMatcher($connection);

    $parts = array('node', '5', 'edit');

    $candidates = $matcher->getCandidateOutlines($parts);

    //debug($candidates);

    $candidates = array_flip($candidates);

    $this->assertTrue(count($candidates) == 4, t('Correct number of candidates found'));
    $this->assertTrue(array_key_exists('/node/5/edit', $candidates), t('First candidate found.'));
    $this->assertTrue(array_key_exists('/node/5/%', $candidates), t('Second candidate found.'));
    $this->assertTrue(array_key_exists('/node/%/edit', $candidates), t('Third candidate found.'));
    $this->assertTrue(array_key_exists('/node/%/%', $candidates), t('Fourth candidate found.'));
  }

  /**
   * Confirms that we can find routes with the exact incoming path.
   */
  function testExactPathMatch() {
    $connection = Database::getConnection();
    $matcher = new PathMatcher($connection, 'test_routes');

    $this->fixtures->createTables($connection);

    $dumper = new MatcherDumper($connection, 'test_routes');
    $dumper->addRoutes($this->fixtures->sampleRouteCollection());
    $dumper->dump();

    $path = '/path/one';

    $request = Request::create($path, 'GET');

    $routes = $matcher->matchRequestPartial($request);

    foreach ($routes as $route) {
      $this->assertEqual($route->getPattern(), $path, t('Found path has correct pattern'));
    }
  }

  /**
   * Confirms that we can find routes whose pattern would match the request.
   */
  function testOutlinePathMatch() {

  }


}
