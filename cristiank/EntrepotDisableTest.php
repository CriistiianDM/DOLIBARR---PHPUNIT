<?php
require_once __DIR__ . '/../../bootstrap.php';
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

class EntrepotDisableTest extends CommonClassTest
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

    public function testDisableWarehouseWhenEmptyAllowed()
    {
        global $db, $user;

        // crear almacén vacío
        $w = new Entrepot($db);
        $w->initAsSpecimen();
        $w->label .= ' phpunit-empty';
        $wid = $w->create($user);
        $this->assertGreaterThan(0, $wid);

        // intentar eliminar/desactivar (sin stock ni movimientos) -> debe permitir
        $res = $w->delete($user);
        $this->assertGreaterThan(0, $res, 'Se debe poder eliminar/desactivar un almacén vacío');
    }

    public function testDisableWarehouseWithStockDenied()
    {
        global $db, $user;

        // crear producto y almacén
        $p = new Product($db);
        $p->initAsSpecimen();
        $p->ref .= ' phpunit-stock';
        $p->label .= ' phpunit-stock';
        $pid = $p->create($user);
        $this->assertGreaterThan(0, $pid);

        $w = new Entrepot($db);
        $w->initAsSpecimen();
        $w->label .= ' phpunit-with-stock';
        $wid = $w->create($user);
        $this->assertGreaterThan(0, $wid);

        // crear movimiento de recepción para dejar stock en el almacén
        $m = new MouvementStock($db);
        $resIn = $m->reception($user, $pid, $wid, 5, 10.0, 'init stock', dol_mktime(0,0,0,1,1,2020));
        $this->assertGreaterThan(0, $resIn);

        // intentar eliminar/desactivar -> debe denegarse (res <= 0 o código negativo)
        $resDel = $w->delete($user);
        $this->assertTrue(!($resDel > 0), 'No se debe poder eliminar/desactivar un almacén con stock/movimientos');
    }
}