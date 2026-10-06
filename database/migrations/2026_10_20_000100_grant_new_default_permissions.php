<?php

use App\Actions\Schools\ProvisionSchoolRoles;
use Illuminate\Database\Migrations\Migration;

/** Existing schools' roles get the default permissions added in this release. */
return new class extends Migration
{
    public function up(): void
    {
        ProvisionSchoolRoles::grantMissingDefaults();
    }

    public function down(): void {}
};
