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

class StockInsufficientQuantityTest extends CommonClassTest
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

    public function testCannotDeliverMoreThanAvailable()
    {
        global $conf,$user,$langs,$db;

        // crear producto (sin lotes)
        $p = new Product($db);
        $p->initAsSpecimen();
        $p->ref .= ' phpunit-insufficient';
        $p->label .= ' phpunit-insufficient';
        $p->status_batch = 0;
        $pid = $p->create($user);
        $this->assertGreaterThan(0, $pid);

        // crear almacén
        $w = new Entrepot($db);
        $w->initAsSpecimen();
        $w->label .= ' phpunit-insuff';
        $wid = $w->create($user);
        $this->assertGreaterThan(0, $wid);

        $m = new MouvementStock($db);
        $dateNow = dol_mktime(0,0,0,1,1,2020);

        // cargar stock inicial: 5 unidades
        $resIn = $m->reception($user, $pid, $wid, 5, 10.0, 'initial stock', $dateNow);
        $this->assertGreaterThan(0, $resIn);

        // intentar entregar 10 unidades (más que el stock disponible)
        $resOut = $m->livraison($user, $pid, $wid, 10, 10.0, 'attempt over-deliver', $dateNow);

        // Esperamos que NO sea un éxito (>0). Ajustar si en tu versión retorna un código específico.
        $this->assertFalse($resOut > 0, 'No se debe permitir entregar más cantidad de la disponible en stock');

        // Comprobar que el stock real no cambió (sigue siendo 5)
        $prodAfter = new Product($db);
        $prodAfter->fetch($pid);
        $this->assertEquals(5, (int)$prodAfter->stock_reel, 'El stock no debe haberse reducido tras intento fallido');
    }
}