@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <svg class="w-8 h-8 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h1 class="text-2xl font-bold text-red-800">Invalid License</h1>
            </div>
            
            <div class="text-red-700 mb-6">
                <p class="mb-4">{{ session('error', 'Your license is invalid or has expired.') }}</p>
                
                @if(session('license_data'))
                    @php $licenseData = session('license_data'); @endphp
                    
                    @if(isset($licenseData['license_status']))
                        <div class="mb-4">
                            <p><strong>License Status:</strong> {{ ucwords($licenseData['license_status']) }}</p>
                            @if(isset($licenseData['license_tier']))
                                <p><strong>License Tier:</strong> {{ ucwords($licenseData['license_tier']) }}</p>
                            @endif
                        </div>
                    @endif
                    
                    @if(isset($licenseData['expired_at']))
                        <div class="mb-4">
                            <p><strong>Expired:</strong> {{ \Carbon\Carbon::parse($licenseData['expired_at'])->format('M j, Y') }}</p>
                            @if(isset($licenseData['days_expired']))
                                <p><strong>Days Expired:</strong> {{ $licenseData['days_expired'] }}</p>
                            @endif
                        </div>
                    @endif
                    
                    @if(isset($licenseData['violations']) && !empty($licenseData['violations']))
                        <div class="mb-4">
                            <p class="font-semibold mb-2">License Violations:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($licenseData['violations'] as $violation)
                                    <li>{{ $violation['message'] ?? 'Unknown violation' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            <div class="space-y-4">
                <a href="{{ route('license.renew') }}" 
                   class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                    Renew License
                </a>
                
                <a href="{{ route('license.contact') }}" 
                   class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors ml-4">
                    Contact Support
                </a>
                
                <a href="{{ route('dashboard') }}" 
                   class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors ml-4">
                    Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection