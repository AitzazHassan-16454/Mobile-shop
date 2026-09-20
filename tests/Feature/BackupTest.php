<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
});

test('authenticated user can download 1-click local sqlite database backup', function () {
    $response = $this->actingAs($this->user)
        ->get(route('backup.download', $this->team->slug));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/x-sqlite3');
    expect($response->headers->get('content-disposition'))->toContain('attachment; filename=faizan_mobile_pos_backup_');
});
