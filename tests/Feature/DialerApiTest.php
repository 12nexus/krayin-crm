<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The 12Nexus Dialer extension's API (api/dialer/*): sign-in, the token guard,
 * and that a lead logged from ViciDial goes through the same rules as the web
 * forms.
 *
 * Every test runs inside a rolled-back transaction and creates the role and
 * user it needs.
 */
uses(DatabaseTransactions::class);

function dialerUser(array $permissions = ['leads', 'leads.create', 'leads.view', 'leads.edit', 'activities', 'activities.create', 'sales_form'], int $status = 1): User
{
    $role = Role::create([
        'name'            => 'Dialer Probe Role '.bin2hex(random_bytes(6)),
        'description'     => 'Created by the dialer API tests.',
        'permission_type' => 'custom',
        'permissions'     => $permissions,
    ]);

    return User::create([
        'name'            => 'Dialer Probe',
        'email'           => 'dialer-probe-'.bin2hex(random_bytes(6)).'@example.invalid',
        'password'        => bcrypt('dialer-password'),
        'status'          => $status,
        'role_id'         => $role->id,
        'view_permission' => 'individual',
    ]);
}

function dialerToken(User $user): string
{
    return test()->postJson(route('api.dialer.login'), [
        'email'    => $user->email,
        'password' => 'dialer-password',
    ])->assertOk()->json('token');
}

it('refuses a wrong password', function () {
    $user = dialerUser();

    test()->postJson(route('api.dialer.login'), [
        'email'    => $user->email,
        'password' => 'not-the-password',
    ])->assertStatus(422);
});

it('gives a sales executive a token, permissions and the form options', function () {
    $user = dialerUser();

    test()->postJson(route('api.dialer.login'), [
        'email'    => $user->email,
        'password' => 'dialer-password',
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('permissions.meeting', true)
        ->assertJsonPath('permissions.new_lead', true)
        ->assertJsonPath('permissions.reassign', false)
        ->assertJsonStructure(['token', 'expires_at', 'options' => ['firms', 'timezones', 'dispositions'], 'crm_url']);
});

it('refuses a sign-in that cannot create leads', function () {
    $user = dialerUser(['dashboard']);

    test()->postJson(route('api.dialer.login'), [
        'email'    => $user->email,
        'password' => 'dialer-password',
    ])->assertStatus(403);
});

it('refuses a disabled account', function () {
    $user = dialerUser(status: 0);

    test()->postJson(route('api.dialer.login'), [
        'email'    => $user->email,
        'password' => 'dialer-password',
    ])->assertStatus(403);
});

it('needs a dialer token on every other call', function () {
    $user = dialerUser();

    test()->getJson(route('api.dialer.me'))->assertStatus(401);

    test()->withToken('1|not-a-real-token')->getJson(route('api.dialer.me'))->assertStatus(401);

    // A token minted for anything else is not a dialer token.
    $other = $user->createToken('other', ['something-else'])->plainTextToken;

    test()->withToken($other)->getJson(route('api.dialer.me'))->assertStatus(401);
});

it('stops accepting the token once the user is disabled', function () {
    $user = dialerUser();
    $token = dialerToken($user);

    test()->withToken($token)->getJson(route('api.dialer.me'))->assertOk();

    $user->update(['status' => 0]);

    auth()->guard('user')->forgetUser();

    test()->withToken($token)->getJson(route('api.dialer.me'))->assertStatus(401);
});

it('revokes the token on logout', function () {
    $user = dialerUser();
    $token = dialerToken($user);

    test()->withToken($token)->postJson(route('api.dialer.logout'))->assertOk();

    auth()->guard('user')->forgetUser();

    test()->withToken($token)->getJson(route('api.dialer.me'))->assertStatus(401);
});

it('reads the dispositions from DIALER_DISPOSITIONS', function () {
    config(['sales_form.dialer.dispositions' => 'SALE:meeting, callbk:lead,IMCB']);

    $user = dialerUser();

    test()->withToken(dialerToken($user))
        ->getJson(route('api.dialer.me'))
        ->assertOk()
        ->assertJsonPath('options.dispositions', ['SALE' => 'meeting', 'CALLBK' => 'lead', 'IMCB' => 'meeting']);
});

it('validates a lead with a meeting by the sales form rules', function () {
    $user = dialerUser();

    test()->withToken(dialerToken($user))
        ->postJson(route('api.dialer.leads.meeting'), ['phone' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lead_name', 'firm', 'state', 'email', 'meeting_date', 'meeting_time', 'timezone', 'phone']);
});

it('creates a lead without a meeting, owned by the rep', function () {
    $user = dialerUser();

    $response = test()->withToken(dialerToken($user))
        ->postJson(route('api.dialer.leads.store'), [
            'user_id'   => $user->id,
            'phone'     => '(303) 555-0142',
            'lead_name' => 'Dialer Probe Lead',
            'firm'      => 'other',
            'brokerage' => 'Probe Realty',
            'note'      => 'Logged by the dialer API test.',
        ])
        ->assertCreated()
        ->assertJsonStructure(['message', 'lead' => ['id', 'title', 'url']]);

    $lead = DB::table('leads')->find($response->json('lead.id'));

    expect($lead->user_id)->toBe($user->id);
});
