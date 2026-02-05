<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class SubscriptionPagesController extends Controller
{
    public function product(string $typeSlug)
    {
        $uid   = auth()->id();
        $today = now()->toDateString();

        $items = DB::table('sub_change_requests as scr')
            ->join('draft_orders as d', 'd.change_request_id', 'scr.id')
            ->join('draft_order_items as di', 'di.draft_order_id', 'd.id')
            ->join('products as p', 'p.product_id', 'di.product_id')
            ->where('scr.for_user_id', $uid)
            ->where('d.status', 'active')
            ->where('scr.start_date', '<=', $today)
            ->where(fn($q) => $q->whereNull('scr.end_date')->orWhere('scr.end_date', '>=', $today))
            ->whereRaw('LOWER(REPLACE(p.product_type," ","-")) = ?', [$typeSlug])
            ->select('di.id','di.qty','di.unit','p.title as product_title')
            ->orderBy('di.id')
            ->get();

        $typeName = ucwords(str_replace('-', ' ', $typeSlug));
        return view('subscriptions.product', compact('typeName','items'));
    }

    public function service(string $typeSlug)
    {
        $uid   = auth()->id();
        $today = now()->toDateString();

        $items = DB::table('sub_change_requests as scr')
            ->join('draft_orders as d', 'd.change_request_id', 'scr.id')
            ->join('draft_order_items as di', 'di.draft_order_id', 'd.id')
            ->join('services as s', 's.service_id', 'di.product_id')
            ->where('scr.for_user_id', $uid)
            ->where('d.status', 'active')
            ->where('scr.start_date', '<=', $today)
            ->where(fn($q) => $q->whereNull('scr.end_date')->orWhere('scr.end_date', '>=', $today))
            ->whereRaw('LOWER(REPLACE(s.service_type," ","-")) = ?', [$typeSlug])
            ->select('di.id','di.qty','di.unit','s.title as service_title')
            ->orderBy('di.id')
            ->get();

        $typeName = ucwords(str_replace('-', ' ', $typeSlug));
        return view('subscriptions.service', compact('typeName','items'));
    }
}
