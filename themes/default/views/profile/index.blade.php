@extends('layouts.main')

@section('content')
    <!-- CONTENT HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="mb-2 row">
                <div class="col-sm-6">
                    <h1>{{ __('Profile') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a class="text-muted"
                                href="{{ route('profile.index') }}">{{ __('Profile') }}</a>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <!-- END CONTENT HEADER -->

    <!-- MAIN CONTENT -->
    <section class="content" x-data>
        <div class="container-fluid">

            <div class="row">
                <div class="px-0 col-lg-12">
                    @if (!Auth::user()->hasVerifiedEmail() && $force_email_verification)
                        <div class="p-2 m-2 alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-circle"></i>{{ __('Required Email verification!') }}
                            </h5>
                            {{ __('You have not yet verified your email address') }}
                            <a class="text-primary"
                                href="{{ route('verification.send') }}">{{ __('Click here to resend verification email') }}</a>
                            <br>
                            {{ __('Please contact support If you didnt receive your verification email.') }}

                        </div>
                    @endif

                    @if (is_null(Auth::user()->discordUser) && $force_discord_verification)
                        @if (!empty($discord_client_id) && !empty($discord_client_secret))
                            <div class="p-2 m-2 alert alert-warning">
                                <h5>
                                    <i class="icon fas fa-exclamation-circle"></i>{{ __('Required Discord verification!') }}
                                </h5>
                                {{ __('You have not yet verified your discord account') }}
                                <a class="text-primary" href="{{ route('auth.redirect') }}">{{ __('Login with discord') }}</a> <br>
                                {{ __('Please contact support If you face any issues.') }}
                            </div>
                        @else
                            <div class="p-2 m-2 alert alert-danger">
                                <h5>
                                    <i class="icon fas fa-exclamation-circle"></i>{{ __('Required Discord verification!') }}
                                </h5>
                                {{ __('Due to system settings you are required to verify your discord account!') }} <br>
                                {{ __('It looks like this hasnt been set-up correctly! Please contact support.') }}
                            </div>
                        @endif
                    @endif

                </div>
            </div>

            <form class="form" action="{{ route('profile.update', Auth::user()->id) }}" method="post">
                @csrf
                @method('PATCH')
                <div class="card">
                    <div class="card-body">
                        <div class="e-profile">
                            <div class="row">
                                <div class="mb-4 col-12 col-sm-auto">
                                    <div class="border slim rounded-circle border-secondary text-gray-dark"
                                        data-label="Change your avatar" data-max-file-size="3"
                                        data-save-initial-image="true" style="width: 140px;height:140px; cursor: pointer"
                                        data-size="140,140">
                                        <img src="{{ $user->getAvatar() }}" alt="avatar">
                                    </div>
                                </div>
                                <div class="mb-3 col d-flex flex-column flex-sm-row justify-content-between">
                                    <div class="mb-2 text-center text-sm-left mb-sm-0">
                                        <h4 class="pb-1 mb-0 pt-sm-2 text-nowrap">{{ $user->name }}</h4>
                                        <p class="mb-0">{{ $user->email }}
                                            @if ($user->hasVerifiedEmail())
                                                <i data-toggle="popover" data-trigger="hover" data-content="Verified"
                                                    class="text-success fas fa-check-circle"></i>
                                            @else
                                                <i data-toggle="popover" data-trigger="hover" data-content="Not verified"
                                                    class="text-danger fas fa-exclamation-circle"></i>
                                            @endif

                                        </p>
                                        <div class="mt-1">
                                            <span class="badge badge-primary"><i
                                                    class="mr-2 fa fa-coins"></i>{{ Currency::formatForDisplay($user->credits) }}</span>
                                        </div>

                                        @if ($referral_enabled)
                                            <div class="mt-1">
                                                @can('user.referral')
                                                    <span class="badge badge-success">
                                                        <i class="mr-2 fa fa-user-check"></i>
                                                        {{ __('Referral URL') }} :
                                                        <span onclick="onClickCopy()" id="RefLink" style="cursor: pointer;"
                                                            data-url="{{ route('register') }}?ref={{ $user->referral_code }}">
                                                            {{ route('register') }}?ref={{ $user->referral_code }}
                                                        </span>
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning">
                                                        <i class="mr-2 fa fa-user-check"></i>
                                                        {{ __('You can not see your Referral Code') }}
                                                    </span>
                                                @endcan
                                            </div>
                                        @endif
                                    </div>

                                    <div class="text-center text-sm-right">
                                        @foreach ($user->roles as $role)
                                            <span style='background-color: {{ $role->color }}'
                                                class='badge'>{{ $role->name }}</span>
                                        @endforeach
                                        <div class="text-muted">
                                            <small>{{ $user->created_at->isoFormat('LL') }}</small>
                                        </div>
                                        <div class="text-muted">
                                            <small>
                                                <button class="badge badge-danger" id="confirmDeleteButton"
                                                    type="button">{{ __('Permanently delete my account') }}</button>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="account-tab" data-toggle="tab" href="#account" role="tab"
                                        aria-controls="account" aria-selected="true">{{ __('Account Settings') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab"
                                        aria-controls="security" aria-selected="false">{{ __('Security') }}</a>
                                </li>
                            </ul>
                            <div class="pt-3 tab-content" id="profileTabsContent">
                                <!-- Account Settings Tab -->
                                <div class="tab-pane fade show active" id="account" role="tabpanel"
                                    aria-labelledby="account-tab">
                                    <div class="row">
                                        <div class="mb-3 col-12 col-sm-6">
                                            <div class="col">
                                                <div class="row">
                                                    <div class="col">
                                                        @if ($errors->has('pterodactyl_error_message'))
                                                            @foreach ($errors->get('pterodactyl_error_message') as $err)
                                                                <span class="text-danger" role="alert">
                                                                    <small><strong>{{ $err }}</strong></small>
                                                                </span>
                                                            @endforeach
                                                        @endif
                                                        @if ($errors->has('pterodactyl_error_status'))
                                                            @foreach ($errors->get('pterodactyl_error_status') as $err)
                                                                <span class="text-danger" role="alert">
                                                                    <small><strong>{{ $err }}</strong></small>
                                                                </span>
                                                            @endforeach
                                                        @endif
                                                        <div class="form-group"><label>{{ __('Name') }}</label> <input
                                                                class="form-control @error('name') is-invalid @enderror"
                                                                type="text" name="name" placeholder="{{ $user->name }}"
                                                                value="{{ $user->name }}">

                                                            @error('name')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="form-group"><label>{{ __('Email') }}</label> <input
                                                                class="form-control @error('email') is-invalid @enderror"
                                                                type="text" placeholder="{{ $user->email }}" name="email"
                                                                value="{{ $user->email }}">

                                                            @error('email')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3 col-12 col-sm-6">
                                            @if (!empty($discord_client_id) && !empty($discord_client_secret))
                                                <div class="row">
                                                    <div class="mb-3 col-12">
                                                        @if (is_null(Auth::user()->discordUser))
                                                            <div class="verify-discord">
                                                                <b>{{ __('Link your discord account!') }}</b>
                                                                <div class="mb-3">
                                                                    @if ($credits_reward_after_verify_discord)
                                                                        <p>
                                                                            {{ __('By verifying your discord account, you receive an extra :amount credits and increased Server amounts', ['amount' => Currency::formatForDisplay($credits_reward_after_verify_discord)]) }}
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                                <a class="btn btn-light" href="{{ route('auth.redirect') }}">
                                                                    <i
                                                                        class="mr-2 fab fa-discord"></i>{{ __('Login with Discord') }}
                                                                </a>
                                                            </div>
                                                        @else
                                                            <div class="verified-discord">
                                                                <div class="pl-2 row">
                                                                    <div class="small-box bg-dark d-inline-block">
                                                                        <div class="d-flex justify-content-between">
                                                                            <div class="p-3">
                                                                                <h3>{{ $user->discordUser->username }}</h3>
                                                                                <p class="mb-0">{{ $user->discordUser->email }}</p>
                                                                                <p class="mb-0 text-muted text-sm">
                                                                                    {{ $user->discordUser->id }}
                                                                                </p>
                                                                            </div>
                                                                            <div class="p-3"><img width="100px" height="100px"
                                                                                    class="rounded-circle"
                                                                                    src="{{ $user->discordUser->getAvatar() }}"
                                                                                    alt="avatar"></div>
                                                                        </div>
                                                                        <div class="small-box-footer">
                                                                            <a href="{{ route('auth.redirect') }}">
                                                                                <i
                                                                                    class="mr-1 fab fa-discord"></i>{{ __('Re-Sync Discord') }}
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Security Tab -->
                                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                                    <div class="row">
                                        <div class="mb-3 col-12 col-sm-6">
                                            <div class="col">
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="form-group">
                                                            <label>{{ __('Current Password') }}</label>
                                                            <input
                                                                class="form-control @error('current_password') is-invalid @enderror"
                                                                name="current_password" type="password"
                                                                placeholder="••••••">

                                                            @error('current_password')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="form-group"><label>{{ __('New Password') }}</label>
                                                            <input
                                                                class="form-control @error('new_password') is-invalid @enderror"
                                                                name="new_password" type="password" placeholder="••••••">

                                                            @error('new_password')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="form-group">
                                                            <label>{{ __('Confirm Password') }}</span></label>
                                                            <input
                                                                class="form-control @error('new_password_confirmation') is-invalid @enderror"
                                                                name="new_password_confirmation" type="password"
                                                                placeholder="••••••">

                                                            @error('new_password_confirmation')
                                                                <div class="invalid-feedback">
                                                                    {{ $message }}
                                                                </div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3 col-12 col-sm-6">
                                            <div class="mb-3"><b>{{ __('Two-Factor Authentication') }}</b></div>

                                            @foreach($availableMethods as $method)
                                                @include($method->getSettingsView(), ['method' => $method])
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 row">
                                <div class="col d-flex justify-content-end">
                                    <button class="btn btn-primary" type="submit">{{ __('Save Changes') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="_token" value="{{ csrf_token() }}">
            </form>

            <form class="form mt-3" x-data="billingForm()" action="{{ route('profile.billing.update') }}" method="post">
                @csrf
                @method('post')
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="mr-2 fas fa-address-card"></i>{{ __('Billing Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            {{ __('These details are used on your invoices and can be updated at any time.') }}
                        </p>

                        <div class="mb-3 btn-group">
                            <button type="button" class="btn btn-sm btn-primary" :class="!isCompany ? 'active' : ''"
                                @click="isCompany = false">{{ __('Individual') }}</button>
                            <button type="button" class="btn btn-sm btn-primary" :class="isCompany ? 'active' : ''"
                                @click="isCompany = true">{{ __('Company') }}</button>
                        </div>
                        <input type="hidden" name="billing_is_company" :value="isCompany ? 1 : 0">

                        <div class="row">
                            <template x-if="!isCompany">
                                <div class="w-100">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ __('First Name') }}</label>
                                            <input type="text" name="billing_first_name" x-model="billing_first_name"
                                                class="form-control @error('billing_first_name') is-invalid @enderror">
                                            @error('billing_first_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ __('Last Name') }}</label>
                                            <input type="text" name="billing_last_name" x-model="billing_last_name"
                                                class="form-control @error('billing_last_name') is-invalid @enderror">
                                            @error('billing_last_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="isCompany">
                                <div class="w-100">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ __('Company Name') }}</label>
                                            <input type="text" name="billing_company_name" x-model="billing_company_name"
                                                class="form-control @error('billing_company_name') is-invalid @enderror">
                                            @error('billing_company_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ __('VAT Number') }}</label>
                                            <input type="text" name="billing_vat_number" x-model="billing_vat_number"
                                                class="form-control @error('billing_vat_number') is-invalid @enderror">
                                            @error('billing_vat_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ __('Phone') }}</label>
                                    <input type="text" name="billing_phone" x-model="billing_phone"
                                        class="form-control @error('billing_phone') is-invalid @enderror">
                                    @error('billing_phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ __('Address') }}</label>
                                    <input type="text" name="billing_address" x-model="billing_address"
                                        class="form-control @error('billing_address') is-invalid @enderror">
                                    @error('billing_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ __('City') }}</label>
                                    <input type="text" name="billing_city" x-model="billing_city"
                                        class="form-control @error('billing_city') is-invalid @enderror">
                                    @error('billing_city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ __('State / Province') }}</label>
                                    <input type="text" name="billing_state" x-model="billing_state"
                                        class="form-control @error('billing_state') is-invalid @enderror">
                                    @error('billing_state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ __('ZIP / Postal Code') }}</label>
                                    <input type="text" name="billing_postal_code" x-model="billing_postal_code"
                                        class="form-control @error('billing_postal_code') is-invalid @enderror">
                                    @error('billing_postal_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{ __('Country') }}</label>
                                    <select name="billing_country" x-model="billing_country"
                                        class="form-control @error('billing_country') is-invalid @enderror">
                                        <option value="">{{ __('Select country...') }}</option>
                                        @foreach ($countries as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('billing_country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col d-flex justify-content-end">
                                <button class="btn btn-primary" type="submit">{{ __('Save Billing Details') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
        <!-- END CUSTOM CONTENT -->

        </div>
    </section>
    <!-- END CONTENT -->
    <script>
        function billingForm() {
            return {
                isCompany: @json((bool) $user->is_company),
                billing_first_name: @json($user->first_name ?? ''),
                billing_last_name: @json($user->last_name ?? ''),
                billing_company_name: @json($user->company_name ?? ''),
                billing_vat_number: @json($user->vat_number ?? ''),
                billing_phone: @json($user->phone ?? ''),
                billing_address: @json($user->address ?? ''),
                billing_city: @json($user->city ?? ''),
                billing_state: @json($user->state ?? ''),
                billing_postal_code: @json($user->postal_code ?? ''),
                billing_country: @json($user->country ?? ''),
            }
        }

        $(document).ready(function () {
            // Check if there is a hash in the URL and show the corresponding tab
            let hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }

            // Update the URL hash when a tab is clicked
            $('.nav-tabs a').on('shown.bs.tab', function (e) {
                history.replaceState(null, null, e.target.hash);
            });
        });

        document.getElementById("confirmDeleteButton").onclick = async () => {
            const {
                value: enterConfirm
            } = await Swal.fire({
                input: 'text',
                inputLabel: '{{ __('Are you sure you want to permanently delete your account and all of your servers?') }} \n Type "{{ __('Delete my account') }}" in the Box below',
                inputPlaceholder: "{{ __('Delete my account') }}",
                showCancelButton: true
            });

            if (enterConfirm === "{{ __('Delete my account') }}") {
                $.ajax({
                    type: "POST",
                    url: "{{ route('profile.selfDestroyUser') }}",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "confirmed": "yes"
                    },
                    success: function (result) {
                        Swal.fire("{{ __('Account has been destroyed') }}", '', 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function (result) {
                        Swal.fire("{{ __('Error') }}", (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : "{{ __('Something went wrong') }}", 'error');
                    }
                });
            } else {
                Swal.fire("{{ __('Account was NOT deleted.') }}", '', 'info');
            }
        }

        function onClickCopy() {
            let textToCopy = document.getElementById('RefLink').getAttribute('data-url');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __('URL copied to clipboard') }}',
                        position: 'top-middle',
                        showConfirmButton: false,
                        background: '#343a40',
                        toast: false,
                        timer: 1000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer)
                            toast.addEventListener('mouseleave', Swal.resumeTimer)
                            toast.addEventListener('click', () => Swal.close())

                        }
                    })
                })
            } else {
                console.log('Browser Not compatible')
            }
        }
    </script>
@endsection
