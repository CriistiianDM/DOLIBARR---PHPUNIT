<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../htdocs/core/class/commonobject.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/class/product.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/stock/class/mouvementstock.class.php';
require_once dirname(__FILE__).'/../../../htdocs/product/stock/class/entrepot.class.php';
require_once dirname(__FILE__).'/../CommonClassTest.class.php';

if (empty($user->id)) {
    print "Load permissions for admin user nb 1\n";
    $user->fetch(1);
    $user->loadRights();
}
$conf->global->MAIN_DISABLE_ALL_MAILS = 1;

class ProductCreateValidationTest extends CommonClassTest
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

    public function testCreateRequiredFieldsAndDuplicateAndNegativeStock()
    {
        global $conf,$user,$langs,$db;

        // 1) Crear producto con atributos obligatorios
        $p = new Product($db);
        $p->initAsSpecimen();
        $unique = time(); // evita colisiones en la suite
        $p->ref = 'PHPUNIT-PROD-' . $unique;
        $p->label = 'Producto PHPUnit ' . $unique;
        // si hay campos obligatorios extra, asignarlos aquí (ej: price, fk_product_type, ...)
        $pid = $p->create($user);
        $this->assertGreaterThan(0, $pid, 'Se debe crear el producto con atributos obligatorios');

        // 2) Intentar crear duplicado con misma ref -> debe fallar
        $p2 = new Product($db);
        $p2->initAsSpecimen();
        $p2->ref = $p->ref; // mismo ref
        $p2->label = 'Duplicado';
        $resDup = $p2->create($user);
        $this->assertFalse($resDup > 0, 'No se debe permitir crear un producto con ref duplicada');

        // 3) Validar que no se pueda dejar stock negativo (stock mínimo)
        // Preparar almacén y recepción inicial 0
        $w = new Entrepot($db);
        $w->initAsSpecimen();
        $w->label .= ' phpunit-stock';
        $wid = $w->create($user);
        $this->assertGreaterThan(0, $wid);

        $m = new MouvementStock($db);
        $dateNow = dol_mktime(0,0,0,1,1,2020);

        // A) recepción positiva para dejar stock 0 (o no registrar)
        // No añadimos recepción para dejar stock 0 explícito.

        // B) intentar crear movimiento de salida mayor a disponible (ej: -5) -> debe fallar
        $resOut = $m->livraison($user, $pid, $wid, 5, 10.0, 'attempt negative stock', $dateNow);
        $this->assertFalse($resOut > 0, 'No se debe permitir generar stock negativo / retirar más de lo disponible');

        // Comprobar que el stock real no quedó por debajo de 0
        $prodAfter = new Product($db);
        $prodAfter->fetch($pid);
        $this->assertGreaterThanOrEqual(0, (int)$prodAfter->stock_reel, 'El stock real no debe ser negativo');

        return true;
    }
}
