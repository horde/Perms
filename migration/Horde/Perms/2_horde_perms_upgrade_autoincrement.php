<?php

class HordePermsUpgradeAutoIncrement extends Horde_Db_Migration_Base
{
    public function up()
    {
        $this->changeColumn('horde_perms', 'perm_id', 'autoincrementKey');
        if (in_array('horde_perms_seq', $this->tables())) {
            $this->dropTable('horde_perms_seq');
        }
    }

    public function down()
    {
        $this->changeColumn('horde_perms', 'perm_id', 'integer', ['null' => false]);
    }
}
