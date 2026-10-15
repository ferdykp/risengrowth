<?php

namespace Tests\Feature;

use App\Models\SearchKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $phrase = 'Butuh   WEBSITE'): array
    {
        return ['phrase'=>$phrase,'platform'=>'threads','category'=>'direct_demand','search_type'=>'RECENT','enabled'=>1,'interval_minutes'=>60,'notes'=>'Testing'];
    }

    public function test_guest_cannot_manage_keywords(): void
    {
        $this->get('/keywords')->assertRedirect('/login');
        $this->post('/keywords',$this->payload())->assertRedirect('/login');
    }

    public function test_owner_can_create_edit_toggle_archive_and_restore_keyword(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('keywords.store'),$this->payload())->assertRedirect(route('keywords.index'));
        $keyword = SearchKeyword::firstOrFail();
        $this->assertSame('butuh website',$keyword->normalized_phrase);
        $this->get(route('keywords.index'))->assertOk()->assertSee('Butuh WEBSITE');
        $this->put(route('keywords.update',$keyword),$this->payload('cari developer'))->assertRedirect(route('keywords.index'));
        $this->patch(route('keywords.toggle',$keyword))->assertRedirect();
        $this->assertFalse($keyword->fresh()->enabled);
        $this->delete(route('keywords.destroy',$keyword))->assertRedirect();
        $this->assertSoftDeleted('search_keywords',['id'=>$keyword->id]);
        $this->post(route('keywords.restore',$keyword->id))->assertRedirect();
        $this->assertNotSoftDeleted('search_keywords',['id'=>$keyword->id]);
    }

    public function test_duplicate_is_rejected_even_when_archived(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('keywords.store'),$this->payload())->assertSessionHasNoErrors();
        $this->post(route('keywords.store'),$this->payload(' butuh website '))->assertSessionHasErrors('phrase');
    }

    public function test_users_cannot_modify_each_others_keywords(): void
    {
        $owner=User::factory()->create();
        $other=User::factory()->create();
        $keyword=SearchKeyword::create(array_merge($this->payload('tes keyword'),['normalized_phrase'=>'tes keyword','user_id'=>$owner->id]));
        $this->actingAs($other)->put(route('keywords.update',$keyword),$this->payload('berubah'))->assertNotFound();
        $this->actingAs($other)->delete(route('keywords.destroy',$keyword))->assertNotFound();
    }
}
