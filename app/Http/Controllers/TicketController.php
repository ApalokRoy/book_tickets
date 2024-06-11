<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DB;

class TicketController extends Controller
{
    public function index() {
        $tickets = Ticket::where('cancelled', false)->pluck('seat_no')->toArray();
        return view('welcome', compact('tickets'));
    }

    public function store(Request $request) {
        $data = Validator::make($request->all(), [
            'parent_seat_no' => 'required|string',
            'name' => 'required|string',
            'count' => 'required|min:1|max:5'
        ]);

        if ($data->fails()) {
            return redirect()->back()->with('danger', $data->errors()->first());
        }

        try {
            DB::beginTransaction();
            $ticket_count = $request->count;
            $parent_seat = $request->parent_seat_no;
            [$row_selected, $column_selected] = explode(',', $parent_seat);
            if ($column_selected - $ticket_count < 1) {
                $first_column = 1;
            } elseif ($column_selected + $ticket_count > 20) {
                $first_column = 20 - $ticket_count;
            } else {
                $first_column = (int)$column_selected;
            }

            $ticket_requested_array = [];
            foreach (range(1, $ticket_count) as $key => $value) {
                $seat_no = $row_selected . "," . ($first_column + $key);
                array_push($ticket_requested_array, $seat_no);
            }

            $seats_booked = Ticket::whereIn('seat_no', $ticket_requested_array)->count();

            if ($seats_booked) {
                foreach (range(1,10) as $r_value) {
                    $seat_count = Ticket::where('seat_no', 'like', $r_value . ",%")->count();
                    if ($seat_count + $ticket_count > 20) {
                        continue;
                    } else {
                        foreach (range('A', 'T') as $c_value) {
                            $col_val = ord($c_value) - 64;
                            $first_available = $r_value . "," . $col_val;
                            if (Ticket::where('seat_no', $first_available)->count() || ($col_val + $ticket_count > 20)) {
                                continue;
                            }
                            
                            $available_slot = [];
                            foreach (range(1, $ticket_count) as $key => $value) {
                                $available_seat_no = $r_value . "," . ($col_val + $key);
                                array_push($available_slot, $available_seat_no);
                            }
                            
                            $seats_available = Ticket::whereIn('seat_no', $available_slot)->count();
                            
                            if (!$seats_available) {
                                $first_available = $r_value . "," . chr($col_val + 64);
                                return redirect()->back()->with('info', "Seat Unavailable, alternate seats available from " . str_replace(",", "", $first_available));
                            }
                        }
                    }
                }
                return redirect()->back()->with('danger', "Seat Unavailable");
            } else {
                foreach ($ticket_requested_array as $value) {
                    $inserted_data['seat_no'] = $value;
                    $inserted_data['name'] = $request->name;
                    $inserted_data['cancelled'] = false;
                    if ($value != $parent_seat) {
                        $inserted_data['parent_seat_no'] = $parent_seat;
                    }

                    Ticket::create($inserted_data);
                }

                DB::commit();
                return redirect()->back()->with('success', "Ticket Booked");
            }
        } catch (\Throwable $th) {
            DB::rollback();
            return redirect()->back()->with('danger', "Something Wrong");
        }
    }
}
