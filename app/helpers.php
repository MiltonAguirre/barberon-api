<?php
use App\Color;
use App\ForeignExchange;

function isBarber(){
    return auth()->user()->role->name=="barber";
}
function isClient(){
    return auth()->user()->role->name == "client";
}