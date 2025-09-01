@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center mb-4">
                <svg class="w-8 h-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h1 class="text-2xl font-bold text-yellow-800">License Upgrade Required</h1>
            </div>
            
            <div class="text-yellow-700 mb-6">
                <p class="mb-4">{{ session('error', 'Your current license does not include the required features for this operation.') }}</p>
                
                @if(session('license_data'))
                    @php $licenseData = session('license_data'); @endphp
                    
                    @if(isset($licenseData['license_tier']))
                        <div class="mb-4">
                            <p><strong>Current License Tier:</strong> {{ ucwords($licenseData['license_tier']) }}</p>
                        </div>
                    @endif
                    
                    @if(isset($licenseData['missing_features']) && !empty($licenseData['missing_features']))
                        <div class="mb-4">
                            <p class="font-semibold mb-2">Missing Features:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($licenseData['missing_features'] as $feature)
                                    <li>{{ ucwords(str_replace('_', ' ', $feature)) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    
                    @if(isset($licenseData['available_features']) && !empty($licenseData['available_features']))
                        <div class="mb-4">
                            <p class="font-semibold mb-2">Your Current Features:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($licenseData['available_features'] as $feature)
                                    <li>{{ ucwords(str_replace('_', ' ', $feature)) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            <div class="space-y-4">
                <a href="{{ route('license.upgrade') }}" 
                   class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors">
                    Upgrade License
                </a>
                
                <a href="{{ route('license.compare') }}" 
                   class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors ml-4">
                    Compare Plans
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