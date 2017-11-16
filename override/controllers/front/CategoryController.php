<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
*  @author Brien <brien@grupoinspiral.com>
*  @copyright  2017 Inspiral Group SL
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CategoryController extends CategoryControllerCore
{
    /**
     * Assigns product list template variables
     */
    public function assignProductList()
    {
        $hook_executed = false;
        Hook::exec('actionProductListOverride', array(
            'nbProducts'   => &$this->nbProducts,
            'catProducts'  => &$this->cat_products,
            'hookExecuted' => &$hook_executed,
        ));

        // The hook was not executed, standard working
        if (!$hook_executed) {
            $this->context->smarty->assign('categoryNameComplement', '');
            $this->nbProducts = $this->category->getProducts(null, null, null, $this->orderBy, $this->orderWay, true);
            $this->pagination((int)$this->nbProducts); // Pagination must be call after "getProducts"
            $this->cat_products = $this->category->getProducts($this->context->language->id, (int)$this->p, (int)$this->n, $this->orderBy, $this->orderWay);
        }
        // Hook executed, use the override
        else {
            // Pagination must be call after "getProducts"
            $this->pagination($this->nbProducts);
        }
        
        $this->addColorsToProductList($this->cat_products);

        Hook::exec('actionProductListModifier', array(
            'nb_products'  => &$this->nbProducts,
            'cat_products' => &$this->cat_products,
        ));

        foreach ($this->cat_products as &$product) {
            $product['name'] = "Funda para " . Tools::safeOutput($this->category->name) . " " . str_replace("Funda para móvil ", "", $product['name']);

            if (isset($product['id_product_attribute']) && $product['id_product_attribute'] && isset($product['product_attribute_minimal_quantity'])) {
                $product['minimal_quantity'] = $product['product_attribute_minimal_quantity'];
            }
        }

        // New code for dulcissimo:
        //http://dulcissimo.com/index.php?controller=product&id_product=
        $mylangid = $this->context->language->id;

        $marcas = AttributeGroup::getAttributes($this->context->language->id, 4);
        $modelos = AttributeGroup::getAttributes($this->context->language->id, 5);

        $catName = Tools::safeOutput($this->category->name);

        $catNameArray = explode(" ", $catName);
        $onlyModel = str_replace($catNameArray[0] . " " , "", $catName);

        // var_dump($catNameArray[0]);
        // var_dump($onlyModel);

       
        $marcaskey = array_search($catNameArray[0], array_column($marcas, 'name'));
        $modelskey = array_search($onlyModel, array_column($modelos, 'name'));

        // var_dump($marcaskey);
        // var_dump($modelskey);

        //Is this a category that matches up with attributes in group 4 (marcas) or 5 (modelos)
        if($marcaskey !== false && $modelskey)
        {
            $marca_id = $marcas[$marcaskey]['id_attribute'];
            $model_id = $modelos[$modelskey]['id_attribute'];

            $catNameArray[0] = strtolower($catNameArray[0]);
            $onlyModel = strtolower($onlyModel);
            str_replace(" ", "-", $onlyModel);

            $sql="SELECT * FROM "._DB_PREFIX_."bsm_attribute_image WHERE id_attribute=".(int)($model_id);
            

            //model is not in db:
            if( !($attribute_images = Db::getInstance()->executeS($sql)) )
            {
                foreach ($this->cat_products as &$product) 
                {
                    $myProd = new Product($product['id_product'], $this->context->language->id);
                    $attributes = $myProd->getAttributeCombinations($this->context->language->id);

                    $attkey = array_search($model_id, array_column($attributes, 'id_attribute'));

                    $attributeImage = Product::getCombinationImageById($attributes[$attkey]['id_product_attribute']
                        , $this->context->language->id);
                    $product['id_image'] = (string)$attributeImage['id_image'];
                    $product['id_product_attribute'] = (string)$attributes[$attkey]['id_product_attribute'];

                    Db::getInstance()->insert('bsm_attribute_image', array(
                    'id_product'    => (int)$product['id_product'],
                    'id_attribute'  => (int)$model_id,
                    'id_image'      => (int)$product['id_image'],
                    'id_product_attribute' => (int)$product['id_product_attribute']
                    ));

                    $split = explode("#", $product['link']);
                    $product['link'] = $split[0];
                    $product['link'] .= "#/" . $marca_id . "-marca-" . $catNameArray[0] . "/" . $model_id . "-movil-" . $onlyModel;
                }

            }
            //model is in db:
            else
            {
                foreach ($this->cat_products as &$product) 
                {
                    $productID = $product['id_product'];
                    $attImageKey = array_search($productID, array_column($attribute_images, 'id_product'));
                    $product['id_image'] = (string)$attribute_images[$attImageKey]['id_image'];
                    $product['id_product_attribute'] = (string)$attribute_images[$attImageKey]['id_product_attribute'];

                    $split = explode("#", $product['link']);
                    $product['link'] = $split[0];
                    $product['link'] .= "#/" . $marca_id . "-marca-" . $catNameArray[0] . "/" . $model_id . "-movil-" . $onlyModel;
                    //var_dump($product);
                }
            }
        }
        
        $this->context->smarty->assign('nb_products', $this->nbProducts);
    }

    public function getProductImage($id_product_attribute, $id_product)
    {
      if (isset($id_product_attribute) && $id_product_attribute) {
            $id_image = Db::getInstance()->getValue('
                SELECT `image_shop`.id_image
                FROM `'._DB_PREFIX_.'product_attribute_image` pai'.
                Shop::addSqlAssociation('image', 'pai', true).'
                LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_image` = pai.`id_image`)
                WHERE id_product_attribute = '.(int)$id_product_attribute. ' ORDER by i.position ASC');
        }
        if (!isset($id_image) || !$id_image) {
            $id_image = Db::getInstance()->getValue('
                SELECT `image_shop`.id_image
                FROM `'._DB_PREFIX_.'image` i'.
                Shop::addSqlAssociation('image', 'i', true, 'image_shop.cover=1').'
                WHERE i.id_product = '.(int)$id_product
            );
        }
        
        return $id_image;
    }
    // END New code for dulcissimo.
}
