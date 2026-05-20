<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_code' => $this->site_code,
            'location_name' => $this->location_name,
            'city' => $this->city,
            'province' => $this->province,
            'site_type' => $this->siteType ? [
                'id' => $this->siteType->id,
                'name' => $this->siteType->name,
            ] : null,
            'machine_type' => $this->machineType ? [
                'id' => $this->machineType->id,
                'name' => $this->machineType->name,
            ] : null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'assignments' => $this->assignments->map(fn ($a) => [
                'id' => $a->id,
                'activity_type' => $a->activity_type->value,
                'status' => $a->status->value,
                'updated_at' => $a->updated_at?->toISOString(),
                'construction_data' => $a->constructionData ? [
                    'cons_wo_number' => $a->constructionData->cons_wo_number,
                ] : null,
            ]),
        ];
    }
}
