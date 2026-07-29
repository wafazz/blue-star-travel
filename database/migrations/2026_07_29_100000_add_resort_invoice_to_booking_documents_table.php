<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setTypeEnum([
            'invoice', 'voucher', 'receipt', 'flight_ticket', 'hotel_voucher',
            'visa', 'insurance', 'payment_slip', 'confirmation', 'other', 'resort_invoice',
        ]);
    }

    public function down(): void
    {
        // The resort invoice is internal — deleting the rows is the point of the rollback,
        // and leaving them would have MySQL truncate them to '' on the MODIFY below.
        DB::table('booking_documents')->where('type', 'resort_invoice')->delete();

        $this->setTypeEnum([
            'invoice', 'voucher', 'receipt', 'flight_ticket', 'hotel_voucher',
            'visa', 'insurance', 'payment_slip', 'confirmation', 'other',
        ]);
    }

    // APPENDED to the end of the list. MySQL stores enums by ordinal, so slipping the
    // new value in mid-list would silently re-label every existing row.
    private function setTypeEnum(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE booking_documents MODIFY type ENUM({$list}) NOT NULL DEFAULT 'other'");

            return;
        }

        Schema::table('booking_documents', function (Blueprint $table) use ($values) {
            $table->enum('type', $values)->default('other')->change();
        });
    }
};
