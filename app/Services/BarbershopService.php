<?php

namespace App\Services;

use App\Models\Barbershop;

trait BarbershopService
{

    public function __construct()
    {
    }
    public function serviceGetBarbershops()
    {
        $barbershops = Barbershop::join('locations', 'locations.id', 'barbershops.location_id')
                                ->select('barbershops.*', 'locations.zip', 'locations.country')->get();
        foreach($barbershops as $barbershop){
            $barbershop['schedules'] = $barbershop->schedules;
        }
        return $barbershops;
    }
}
