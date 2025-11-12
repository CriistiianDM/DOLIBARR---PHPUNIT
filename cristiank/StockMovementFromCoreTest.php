<?php
require_once __DIR__ . '/../../bootstrap.php';

// require_once dirname(__FILE__).'/../../../htdocs/master.inc.php';
require_once __DIR__ . '/../../../htdocs/core/class/commonobject.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/stock/class/mouvementstock.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/stock/class/entrepot.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/class/product.class.php';
require_once dirname(__FILE__).'/../CommonClassTest.class.php';

if (empty($user->id)) {
    print "Load permissions for admin user nb 1\n";
    $user->fetch(1);
    $user->loadRights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS = 1;

class StockMovementFromCoreTest extends CommonClassTest
{
    protected function setUp(): void
    {
        global $conf,$user,$langs,$db;
        $conf = $this->savconf;
        $user = $this->savuser;
        $langs = $this->savlangs;
        $db = $this->savdb;

        if (!isModEnabled('product')) {
            $this->markTestSkipped('module product not enabled');
        }
    }

    public function testReceptionPaths()
    {
        global $conf,$user,$langs,$db;

        // crear producto loteable
        $p = new Product($db);
        $p->initAsSpecimen();
        $p->ref .= ' phpunit-paths';
        $p->label .= ' phpunit-paths';
        $p->status_batch = 1;
        $pid = $p->create($user);
        $this->assertGreaterThan(0, $pid);

        // crear almacén
        $w = new Entrepot($db);
        $w->initAsSpecimen();
        $w->label .= ' phpunit-p';
        $wid = $w->create($user);
        $this->assertGreaterThan(0, $wid);

        $m = new MouvementStock($db);
        $dateA = dol_mktime(0,0,0,1,1,2000);
        $dateB = dol_mktime(0,0,0,1,2,2000);

        // Path 1: crear movimiento con lote A -> OK
        $res1 = $m->reception($user, $pid, $wid, 5, 9.9, 'mvt A', $dateA, $dateA, 'LOT-A', '', 0, 'IC-A');
        $this->assertGreaterThan(0, $res1);

        // Path 2: mismo lote con eatby distinto -> -3
        $res2 = $m->reception($user, $pid, $wid, 5, 9.9, 'mvt A diff eatby', $dateB, $dateA, 'LOT-A', '', 0, 'IC-A2');
        $this->assertGreaterThan(0, $res2);

        // Path 3: lote B con precio distinto -> OK
        $res3 = $m->reception($user, $pid, $wid, 10, 8.7, 'mvt B', '', '', 'LOT-B', '', 0, 'IC-B');
        $this->assertGreaterThan(0, $res3);

        // comprobar PMP y stock
        $prod = new Product($db);
        $prod->fetch($pid);
        $this->assertGreaterThan(0, $prod->stock_reel);
        $this->assertNotNull($prod->pmp);

        return true;
    }
}