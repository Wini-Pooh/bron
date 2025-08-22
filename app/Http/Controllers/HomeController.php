<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable|\Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        $user = auth()->user();
        
        // Ищем компанию пользователя
        $company = $user->company;
        
        if ($company && $company->is_active) {
            return redirect()->route('company.show', $company->slug);
        }
        
        // Если у пользователя нет компании, показываем welcome страницу или создаем компанию
        return view('welcome');
    }
}
