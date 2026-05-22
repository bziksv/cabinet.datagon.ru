<?php

namespace App\Http\Controllers;

use App\Classes\Tariffs\Facades\Tariffs;
use App\Classes\Tariffs\FreeTariff;
use App\TariffSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TariffSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    /** @return array<string, string> */
    protected function tariffLabels(): array
    {
        $labels = [];
        foreach ((new Tariffs())->getTariffs() as $tariff) {
            $labels[$tariff->code()] = $tariff->name();
        }
        $labels[(new FreeTariff())->code()] = (new FreeTariff())->name();

        return $labels;
    }

    public function index(): View
    {
        $settings = TariffSetting::with(['fields' => static function ($query) {
            $query->orderBy('sort');
        }])->orderBy('name')->get();

        $valuesCount = $settings->sum(static function (TariffSetting $setting) {
            return $setting->fields->count();
        });

        return view('tariff-settings.index', [
            'settings' => $settings,
            'tariffLabels' => $this->tariffLabels(),
            'stats' => [
                'settings' => $settings->count(),
                'values' => $valuesCount,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('tariff-settings.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSetting($request);
        TariffSetting::create($data);

        return redirect()->route('tariff-settings.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(TariffSetting $setting)
    {
        return view('tariff-settings.edit', compact('setting'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(TariffSetting $setting, Request $request): RedirectResponse
    {
        $data = $this->validatedSetting($request, $setting);
        $setting->update($data);

        return redirect()->to(route('tariff-settings.index') . '#' . $setting->code);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(TariffSetting $setting): RedirectResponse
    {
        $setting->delete();

        return redirect()->route('tariff-settings.index');
    }

    protected function validatedSetting(Request $request, ?TariffSetting $setting = null): array
    {
        $codeRule = 'required|regex:/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/|max:191';
        if ($setting) {
            $codeRule .= '|unique:tariff_settings,code,' . $setting->id;
        } else {
            $codeRule .= '|unique:tariff_settings,code';
        }

        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'code' => [$codeRule],
            'description' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ]);
    }
}
