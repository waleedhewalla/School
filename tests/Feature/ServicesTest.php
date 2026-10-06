<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\ClinicVisit;
use App\Models\HealthRecord;
use App\Models\InventoryItem;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\MessageLog;
use App\Models\StudentTransport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private array $d;

    private $school;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = $this->createSchool();
        $this->d = $this->seedSchoolData($this->school);
        $this->parent = $this->memberOf($this->school, SchoolRole::Guardian);
        $this->inSchool($this->school, fn () => $this->d['students'][0]->guardians()->first()->forceFill(['user_id' => $this->parent->id, 'phone' => '0551112222'])->save());
    }

    public function test_nurse_records_visits_and_health_cards_and_sending_home_tells_the_guardian(): void
    {
        [$ahmad] = $this->d['students'];
        $this->actingAs($this->memberOf($this->school, SchoolRole::Nurse));

        $this->put("/clinic/students/{$ahmad->id}", ['blood_type' => 'O+', 'allergies' => 'الفول السوداني'])->assertSessionHasNoErrors();
        $this->put("/clinic/students/{$ahmad->id}", ['blood_type' => 'X'])->assertSessionHasErrors('blood_type');

        $this->post('/clinic/visits', ['student_id' => $ahmad->id, 'complaint' => 'صداع', 'temperature' => 37.2, 'outcome' => 'returned_to_class'])->assertSessionHasNoErrors();
        $this->post('/clinic/visits', ['student_id' => $ahmad->id, 'complaint' => 'حرارة', 'temperature' => 38.9, 'outcome' => 'sent_home'])->assertSessionHasNoErrors();

        $this->inSchool($this->school, function () {
            $this->assertSame(2, ClinicVisit::query()->count());
            $sms = MessageLog::query()->where('purpose', 'clinic')->sole();
            $this->assertSame('966551112222', $sms->to);
            $this->assertStringContainsString('ابنكم', $sms->body);
        });

        $this->get('/clinic')->assertInertia(fn (Assert $page) => $page->component('Clinic/Index')->where('today', 2)->has('alerts', 1));
        $this->get("/clinic/students/{$ahmad->id}")->assertInertia(fn (Assert $page) => $page->where('record.allergies', 'الفول السوداني')->has('visits', 2));

        // Teachers and registrars do not see health data.
        $this->actingAs($this->d['teacher'])->get('/clinic')->assertForbidden();
        $this->actingAs($this->memberOf($this->school, SchoolRole::Registrar))->get("/clinic/students/{$ahmad->id}")->assertForbidden();
    }

    public function test_guardians_keep_their_own_childs_health_card(): void
    {
        [$ahmad, $sara] = $this->d['students'];
        $this->actingAs($this->parent);

        $this->put("/my/health/{$ahmad->id}", ['allergies' => 'البنسلين', 'emergency_contact_phone' => '0559998877'])->assertSessionHasNoErrors();
        $this->put("/my/health/{$sara->id}", ['allergies' => 'x'])->assertForbidden();
        $this->assertSame('البنسلين', $this->inSchool($this->school, fn () => HealthRecord::query()->sole()->allergies));
        $this->get('/my/health')->assertInertia(fn (Assert $page) => $page->component('Clinic/MyHealth')->where('children.0.record.allergies', 'البنسلين'));
    }

    public function test_library_lends_within_copies_and_tracks_returns(): void
    {
        $this->actingAs($this->memberOf($this->school, SchoolRole::Librarian));
        $this->post('/library/books', ['title' => 'كليلة ودمنة', 'author' => 'ابن المقفع', 'copies' => 1])->assertSessionHasNoErrors();
        $book = $this->inSchool($this->school, fn () => LibraryBook::query()->sole());
        [$ahmad, $sara] = $this->d['students'];

        $this->post('/library/loans', ['library_book_id' => $book->id, 'borrower' => "student:{$ahmad->id}", 'due_on' => '2026-09-17'])->assertSessionHasNoErrors();
        $this->post('/library/loans', ['library_book_id' => $book->id, 'borrower' => "student:{$sara->id}", 'due_on' => '2026-09-17'])->assertSessionHasErrors('library_book_id');
        $this->put("/library/books/{$book->id}", ['title' => 'كليلة ودمنة', 'copies' => 0])->assertSessionHasErrors('copies');

        $this->actingAs($this->parent)->get('/my/children')->assertInertia(fn (Assert $page) => $page->where('children.0.loans.0.title', 'كليلة ودمنة'));

        $loan = $this->inSchool($this->school, fn () => LibraryLoan::query()->sole());
        $this->travel(20)->days();
        $this->assertTrue($this->inSchool($this->school, fn () => $loan->refresh()->isOverdue()));
        $this->actingAs($this->memberOf($this->school, SchoolRole::Librarian));
        $this->post("/library/loans/{$loan->id}/return")->assertSessionHasNoErrors();
        $this->assertNotNull($this->inSchool($this->school, fn () => $loan->refresh()->returned_on));
    }

    public function test_transport_routes_stops_and_bus_capacity(): void
    {
        $this->actingAs($this->memberOf($this->school, SchoolRole::Registrar));
        $this->post('/transport/buses', ['number' => '7', 'capacity' => 1, 'supervisor_phone' => '0554443333'])->assertSessionHasNoErrors();
        $bus = $this->inSchool($this->school, fn () => Bus::query()->sole());
        $this->post('/transport/routes', ['name' => 'حي النرجس', 'bus_id' => $bus->id])->assertRedirect();
        $route = $this->inSchool($this->school, fn () => BusRoute::query()->sole());

        $this->post("/transport/routes/{$route->id}/stops", ['name' => 'مسجد الحي', 'sequence' => 1, 'pickup_at' => '06:20', 'dropoff_at' => '13:15'])->assertSessionHasNoErrors();
        $stop = $this->inSchool($this->school, fn () => $route->stops()->sole());
        [$ahmad, $sara] = $this->d['students'];

        $this->post("/transport/routes/{$route->id}/riders", ['student_id' => $ahmad->id, 'route_stop_id' => $stop->id])->assertSessionHasNoErrors();
        $this->post("/transport/routes/{$route->id}/riders", ['student_id' => $sara->id])->assertSessionHasErrors('student_id');
        // Changing the stop of a rider already on the bus is fine.
        $this->post("/transport/routes/{$route->id}/riders", ['student_id' => $ahmad->id, 'route_stop_id' => null])->assertSessionHasNoErrors();
        $this->post("/transport/routes/{$route->id}/riders", ['student_id' => $ahmad->id, 'route_stop_id' => $stop->id]);
        $this->delete("/transport/routes/{$route->id}")->assertSessionHasErrors('route');

        $this->get("/transport/routes/{$route->id}")->assertInertia(fn (Assert $page) => $page->component('Transport/Route')->has('riders', 1)->where('stops.0.pickup_at', '06:20'));

        $this->actingAs($this->parent)->get('/my/children')
            ->assertInertia(fn (Assert $page) => $page->where('children.0.transport.bus', '7')->where('children.0.transport.pickup_at', '06:20'));
        $this->assertSame(1, $this->inSchool($this->school, fn () => StudentTransport::query()->count()));
    }

    public function test_inventory_register_and_export(): void
    {
        $this->actingAs($this->memberOf($this->school, SchoolRole::Principal));
        $this->post('/inventory', ['name' => 'جهاز عرض', 'category' => 'أجهزة', 'quantity' => 3, 'condition' => 'good', 'unit_value' => 1500])->assertSessionHasNoErrors();
        $this->post('/inventory', ['name' => 'طاولة', 'quantity' => 2, 'condition' => 'broken'])->assertSessionHasErrors('condition');

        $this->get('/inventory')->assertInertia(fn (Assert $page) => $page->component('Inventory/Index')->where('totals.value', 4500));
        $this->get('/inventory/export')->assertOk()->assertDownload('inventory.xlsx');
        $this->assertSame(1, $this->inSchool($this->school, fn () => InventoryItem::query()->count()));

        $this->actingAs($this->d['teacher'])->get('/inventory')->assertForbidden();
    }
}
