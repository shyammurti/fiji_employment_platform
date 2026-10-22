<x-app-layout title="Register as an employer">
    <x-slot name="header">
        <h1 class="page-title">Register as an employer</h1>
        <p class="page-intro">An administrator checks your business details and registration certificate before you can post vacancies or search candidates. Reviews usually take 1 to 2 business days.</p>
    </x-slot>

    <form method="POST" action="{{ route('employer.register') }}" enctype="multipart/form-data" class="card max-w-3xl space-y-5">
        @csrf
        @include('profile.partials.employer-fields')
        <div class="flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Submit for approval</button>
        </div>
    </form>
</x-app-layout>
