<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker; // Pastikan nama model sesuai dengan file di models
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Exports\WorkersExport;





class WorkerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        $worker = worker::all();
        return view('backend.admin.worker.index', compact('worker'));
    }
    public function data(Request $request)
    {

        if ($request->ajax()) {

            $view = View::make('backend.admin.worker.data')->render();
            return response()->json(['html' => $view]);
        } else {
            return response()->json(['status' => 'false', 'message' => "Access only ajax request"]);
        }
    }

    public function getAll(Request $request)
    {
        if ($request->ajax()) {
            $workers = worker::orderBy('created_at', 'desc')->get();
            return Datatables::of($workers)
                ->addColumn('status', function ($worker) {
                    return $worker->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
                })
                ->addColumn('action', function ($worker) {
                    $html = '<div class="btn-group">';
                    // $html .= '<button data-toggle="tooltip" class="btn btn-xs btn-info mr-1 view" title="View" data-id="' . $worker->id . '"><i class="fa fa-eye"></i></button>';

                    $html .= '<button data-toggle="tooltip" class="btn btn-xs btn-warning mr-1 edit" title="Edit" data-id="' . $worker->id . '"><i class="fa fa-edit"></i></button>';
                    $html .= '<a href="worker/' . $worker->id . '/cetakpdf" data-toggle="tooltip" data-id="' . $worker->id . '" class="btn btn-xs btn-success mr-1 cetakpdf" title="PDF" target="_blank"><i class="fa fa-print"></i> </a>';
                    $html .= '<button data-toggle="tooltip" class="btn btn-xs btn-danger delete" title="Delete" data-id="' . $worker->id . '"><i class="fa fa-trash"></i></button>';
                    $html .= '</div>';
                    return $html;
                })
                ->rawColumns(['action', 'status'])
                ->addIndexColumn()
                ->make(true);
        } else {
            return response()->json(['status' => 'false', 'message' => "Access only ajax request"]);
        }
    }


    public function workers(Request $request)
    {
        // Ambil semua data dari tabel note
        $query = Worker::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('from_date') && $request->has('to_date')) {
            // Menggunakan whereBetween untuk filter rentang tanggal
            $query->whereBetween('created_at', [$request->from_date . ' 00:00:00', $request->to_date . ' 23:59:59']);
        }

        if ($request->has('created_at')) {
            $query->whereDate('created_at', $request->created_at);
        }
        $workers = $query->get();
        // Kembalikan data dalam format JSON
        return response()->json($workers);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if ($request->ajax()) {




            $view = View::make('backend.admin.worker.create')->render();
            return response()->json(['html' => $view]);
        } else {
            return response()->json(['status' => 'false', 'message' => "Access only ajax request"]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validasi input
        $rules = [
            'id' => 'required',
            'name' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // untuk upload file biasa
            'Photo' => 'nullable|string', // untuk base64 image
        ];
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            // Jika validasi gagal, kembalikan ke halaman sebelumnya dengan error
            return redirect()->back()->withErrors($validator)->withInput();
        }
    
        // Simpan file gambar (dari file upload)
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images'), $imageName); // Simpan gambar ke folder 'images'
        }
    
        // Simpan gambar dari base64 jika ada
        if ($request->input('Photo')) {
            $Photo = $request->input('Photo');
            $imageName = time() . '.png'; // Kamu bisa mengganti ekstensi sesuai kebutuhan
            $imagePath = public_path('images/' . $imageName);
            $imageData = explode(',', $Photo)[1]; // Mengambil data base64 setelah `,`
            file_put_contents($imagePath, base64_decode($imageData)); // Menyimpan gambar
        }
    
        // Simpan data worker
        $worker = new Worker();
        $worker->id = $request->input('id');
        $worker->name = $request->input('name');
        $worker->image = $imageName; // Simpan nama file gambar
        $worker->save();
    
        // Redirect ke halaman index setelah berhasil menyimpan data
        return redirect()->route('worker.index')->with('success', 'Worker successfully created');
    }
    










    /**
     * Display the specified resource.
     *
     * @param  \App\Models\worker  $worker
     * @return \Illuminate\Http\Response
     */
    public function show(worker $worker)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\worker  $worker
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        if ($request->ajax()) {

            $worker = worker::find($id);

            $view = View::make('backend.admin.worker.edit', compact('worker'))->render();
            return response()->json(['html' => $view]);
        } else {
            return response()->json(['status' => 'false', 'message' => "Access only ajax request"]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\worker  $worker
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, $id)
    {
        // Cari worker berdasarkan id
        $worker = Worker::find($id);

        if (!$worker) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Worker not found'], 404);
            } else {
                return redirect()->route('worker.index')->with('error', 'Worker not found');
            }
        }

        // Validasi input
        $rules = [
            'name' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // format yang diperbolehkan
            'Photo' => 'nullable|string', // untuk base64
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
            } else {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        // Hapus gambar lama jika ada
        if ($worker->image) {
            $oldImagePath = public_path('images/' . $worker->image);
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath); // Menghapus gambar lama
            }
        }

        // Upload gambar baru dari file jika ada
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images'), $imageName);
            $worker->image = $imageName; // Simpan nama file gambar
        }

        // Atau upload gambar baru dari base64 jika ada
        if ($request->input('Photo')) {
            $Photo = $request->input('Photo');
            $imageName = time() . '.png'; // Ganti ekstensi jika perlu
            $imagePath = public_path('images/' . $imageName);
            $imageData = explode(',', $Photo)[1]; // Mengambil data base64
            file_put_contents($imagePath, base64_decode($imageData));
            $worker->image = $imageName; // Simpan nama file gambar
        }

        // Perbarui nama worker
        $worker->name = $request->input('name');
        $worker->save();

        // Berikan respon sesuai jenis request
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Data worker berhasil diupdate',
                'worker' => $worker
            ], 200);
        } else {
            return redirect()->route('worker.index')->with('success', 'Successfully Updated');
        }
    }

    public function destroy($id)
    {
        $worker = Worker::findOrFail($id);
    
        // Hapus data worker
        $worker->delete();
    
        // Redirect atau pesan success
        return redirect()->route('worker.index')->with('success', 'Worker deleted successfully');
    }
    
    



    public function cetakpdf($id)
    {
        $worker = Worker::find($id);

        $data = [
            'title' => 'Daftar Pekerja',
            'data' => $worker,
        ];

        $pdf = PDF::loadView('backend.admin.worker.worker_pdf', $data);

        return $pdf->stream('worker_' . $id . '.pdf');
    }

    public function exportPdf()
    {
        // Ambil semua pekerja dari database
        $workers = Worker::all();

        // Data tambahan yang dikirim ke view
        $data = [
            'title' => 'Daftar Semua Pekerja',
            'workers' => $workers
        ];

        // Generate PDF menggunakan view 'worker_pdfall'
        $pdf = Pdf::loadView('backend.admin.worker.worker_pdfall', $data);

        // Download PDF dengan nama file yang diinginkan
        return $pdf->stream('workerall_.pdf');
    }


    public function import(Request $request)
    {
        return view('import');
    }


    public function import_proses(Request $request)
    {


        $validatedData = $request->validate([
            'file' => 'required',
        ]);
        Excel::import(new UsersImport, $request->file('file'));
        return redirect()->back();
    }

    public function exportExcel()
    {
        $file_name = 'data.xlsx'; // Nama file yang akan diunduh
        return Excel::download(new WorkersExport, $file_name);
    }
}
