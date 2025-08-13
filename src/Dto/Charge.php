<?php
namespace R2Soft\Canvi\Dto; class Charge{ public function __construct(public int $amount){} public function toArray(){return ['amount'=>$this->amount];}}