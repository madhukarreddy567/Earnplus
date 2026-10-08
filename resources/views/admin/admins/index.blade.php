<?php $title = 'Admin accounts'; ?>
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4" data-animate>
        <div>
            <h1 class="h3 fw-bold mb-0">Admin accounts</h1>
            <p class="text-muted mb-0">Super admin only.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to dashboard</a>
    </div>

    <div class="card shadow-sm border-0 rounded-4" data-animate data-delay="120">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>E-mail</th>
                        <th>Role</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($admins as $admin)
                        <tr>
                            <td>{{ $admin->name }}</td>
                            <td>{{ $admin->email }}</td>
                            <td>
                                @if ($admin->isSuperAdmin())
                                    <span class="badge bg-warning text-dark">super_admin</span>
                                @else
                                    <span class="badge bg-info text-dark">admin</span>
                                @endif
                            </td>
                            <td>
                                @if ($admin->is_active)
                                    <span class="badge bg-success">active</span>
                                @else
                                    <span class="badge bg-secondary">inactive</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
