@extends('layouts.app')

@section('content')
    <div class="toolbar">
        <strong>{{ __('Madrasa') }}</strong>
        <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" aria-label="{{ __('Switch language') }}">
            {{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}
        </a>
    </div>

    <div class="card">
        <h1>{{ __('School management for K-12 schools') }}</h1>
        <p class="muted">{{ __('Students, attendance, grades, fees and parent communication in one place.') }}</p>
        <p><a href="{{ route('login') }}">{{ __('Sign in to your school') }}</a></p>
        <p>{{ __('Today') }}: {{ \App\Support\Dates\SchoolDate::display(now(), \App\Enums\DateDisplay::from(config('madrasa.date_display'))) }}</p>
    </div>
@endsection
