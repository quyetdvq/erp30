<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** P2-CUS-G1: additive, exactly three new entities. No modifications to P1 Contact. */
final class CreateCustomerCoreTables extends Migration
{
    public function up(): void
    {
        $big = ['type'=>'BIGINT', 'unsigned'=>true];
        $this->forge->addField([
            'id'=>$big+['auto_increment'=>true],
            'customer_code'=>['type'=>'VARCHAR','constraint'=>50],
            'name'=>['type'=>'VARCHAR','constraint'=>255],
            'customer_type'=>['type'=>'VARCHAR','constraint'=>20],
            'customer_group_code'=>['type'=>'VARCHAR','constraint'=>50,'null'=>true],
            'phone'=>['type'=>'VARCHAR','constraint'=>50,'null'=>true],
            'email'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'tax_code'=>['type'=>'VARCHAR','constraint'=>50,'null'=>true],
            'address'=>['type'=>'TEXT','null'=>true],
            'province_id'=>$big+['null'=>true],
            'ward_id'=>$big+['null'=>true],
            'primary_contact_id'=>$big+['null'=>true],
            'all_companies'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],
            'status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'ACTIVE'],
            'created_by'=>$big+['null'=>true],
            'updated_by'=>$big+['null'=>true],
            'created_at'=>['type'=>'DATETIME'],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('customer_code');
        $this->forge->addKey(['status','name']);
        $this->forge->addKey('customer_group_code');
        $this->forge->addKey('tax_code');
        $this->forge->addForeignKey('province_id','provinces','id');
        $this->forge->addForeignKey('ward_id','wards','id');
        $this->forge->addForeignKey('primary_contact_id','contacts','id');
        $this->forge->createTable('customers', false, ['ENGINE'=>'InnoDB']);

        $this->forge->addField([
            'customer_id'=>$big,
            'company_organization_id'=>$big,
        ]);
        $this->forge->addPrimaryKey(['customer_id','company_organization_id']);
        $this->forge->addKey(['company_organization_id','customer_id']);
        $this->forge->addForeignKey('customer_id','customers','id');
        $this->forge->addForeignKey('company_organization_id','organizations','id');
        $this->forge->createTable('customer_company_usages',false,['ENGINE'=>'InnoDB']);

        $this->forge->addField([
            'id'=>$big+['auto_increment'=>true],
            'customer_id'=>$big,
            'company_organization_id'=>$big,
            'activity_type'=>['type'=>'VARCHAR','constraint'=>30],
            'content'=>['type'=>'TEXT'],
            'happened_at'=>['type'=>'DATETIME'],
            'created_by'=>$big+['null'=>true],
            'created_at'=>['type'=>'DATETIME'],
            'corrects_interaction_id'=>$big+['null'=>true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['customer_id','company_organization_id','happened_at','id']);
        $this->forge->addKey(['company_organization_id','happened_at']);
        $this->forge->addForeignKey('customer_id','customers','id');
        $this->forge->addForeignKey('company_organization_id','organizations','id');
        $this->forge->addForeignKey('corrects_interaction_id','customer_interactions','id');
        $this->forge->createTable('customer_interactions',false,['ENGINE'=>'InnoDB']);
    }

    public function down(): void
    {
        // Destructive rollback requires an explicit operator review and backup.
        foreach (['customer_interactions','customer_company_usages','customers'] as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new \RuntimeException('CUS_ROLLBACK_REQUIRES_EMPTY_TABLES');
            }
        }
        $this->forge->dropTable('customer_interactions',true);
        $this->forge->dropTable('customer_company_usages',true);
        $this->forge->dropTable('customers',true);
    }
}
