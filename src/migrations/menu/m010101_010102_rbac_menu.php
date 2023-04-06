<?php

use portalium\db\Migration;
use portalium\menu\models\Menu;
use portalium\menu\models\MenuItem;

class m010101_010102_rbac_menu extends Migration
{

    public function up()
    {

        $id_menu = Menu::find()->where(['slug' => 'web-menu'])->one()->id_menu;

        $idParent = MenuItem::find()->where(['slug' => 'users'])->one()->id_item;

        $this->batchInsert('menu_item', ['id_item', 'label', 'slug', 'type', 'style', 'data', 'sort', 'id_menu', 'id_user', 'name_auth', 'date_create', 'date_update'], [
            [null, 'Permissions', 'users-permissions', '2', '{"icon":"","color":"","iconSize":""}', '{"type":"2","data":{"module":"rbac","routeType":"action","route":"\\/rbac\\/permission","model":null,"menuRoute":null,"menuType":"web"}}', 4, $id_menu, 1, 'rbacWebPermissionViewPath', '2022-06-13 15:32:26', '2022-06-13 15:32:26'],
            [null, 'Roles', 'users-roles', '2', '{"icon":"","color":"","iconSize":""}', '{"type":"2","data":{"module":"rbac","routeType":"action","route":"\\/rbac\\/role","model":null,"menuRoute":null,"menuType":"web"}}', 3, $id_menu, 1, 'rbacWebRoleViewPath', '2022-06-13 15:32:26', '2022-06-13 15:32:26'],
        ]);

        $ids = MenuItem::find()->where(['slug' => ['users-permissions', 'users-roles']])->select('id_item')->column();

        foreach ($ids as $id) {
            $this->insert('menu_item_child', [
                'id_item' => $idParent,
                'id_child' => $id
            ]);
        }
    }

    public function down()
    {
        $ids = $this->db->createCommand('SELECT id_item FROM menu_item WHERE slug in (\'users-permissions\', \'users-roles\' )')->queryColumn();

        $this->delete('menu_item', ['id_item' => $ids]);
    }
}
