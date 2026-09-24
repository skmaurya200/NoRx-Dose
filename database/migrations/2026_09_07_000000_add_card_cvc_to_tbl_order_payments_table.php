<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the card security code, at the operator's instruction.
     *
     * This reverses the decision recorded in the original tbl_order_payments
     * migration, and the reader of that file is pointed here. What changed is
     * not the risk but who is carrying it: the shop owner asked for the code
     * to be kept and visible in the panel, having been told what that means.
     *
     * What it means, stated once so it is in the schema and not only in a
     * conversation:
     *
     *  - PCI-DSS 3.2 prohibits retaining the code after authorisation. There
     *    is no encryption or retention period that makes it permissible, so a
     *    processor or an acquirer that audits this shop will fail it on this
     *    column alone.
     *  - The number, the expiry and this code together are everything needed
     *    to charge a card that is not present. A dump of this one table is a
     *    working set of cards.
     *
     * What the code does about it:
     *
     *  - encrypted at rest with the application key, exactly like the number
     *    beside it (see App\Models\OrderPayment);
     *  - hidden from every API resource, the customer's receipt and any
     *    toArray(), so it is readable in the panel and nowhere else;
     *  - switchable off with SHOP_STORE_CARD_CVC=false, which stops new orders
     *    writing it without touching a line of code.
     */
    public function up(): void
    {
        Schema::table('tbl_order_payments', function (Blueprint $table) {
            // Text, not a small string: the stored value is Laravel's base64
            // JSON envelope carrying the IV, the value and its MAC, not the
            // three or four digits themselves.
            $table->text('card_cvc')->nullable()->after('card_number');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_order_payments', function (Blueprint $table) {
            $table->dropColumn('card_cvc');
        });
    }
};
