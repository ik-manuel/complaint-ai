<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ComplaintAI')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-blue-600">ComplaintAI</h1>
                <div class="space-x-4">
                    <a href="{{ route('complaint.create') }}" class="text-gray-600 hover:text-blue-600">Submit Complaint</a>
                    <a href="{{ route('admin.index') }}" class="text-gray-600 hover:text-blue-600">Admin Dashboard</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
        
    </main>

    <footer class="bg-white shadow-lg mt-12">
        <div class="max-w-7xl mx-auto px-4 py-6 text-center text-gray-600">
            <p>ComplaintAI - AI-Powered Customer Service | Week 1 & 2 Concepts Demo</p>
        </div>
    </footer>
</body>
</html>
