<?php

namespace App\Traits;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     * @param array $tags
     * @param array $customTags
     * @param int $photoId
     */
    public function addTags ($tags, $customTags, $photoId)
    {
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);

        // Delete the old tags
        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo);

        // Add the new tags
        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $tags);

        // Add the new custom tags
        /** @var AddCustomTagsToPhotoAction $addCustomTagsAction */
        $addCustomTagsAction = app(AddCustomTagsToPhotoAction::class);
        $customTagsTotal = $addCustomTagsAction->run($photo, $customTags);

        // Decrement the XP since old tags no longer exist
        $xpDifference = $litterTotals['all'] + $customTagsTotal - $deletedTags['all'];

        $user->xp += $xpDifference;
        $user->xp = max(0, $user->xp);
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $litterTotals['litter'];
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();

        // Update the Leaderboards
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, $xpDifference);
    }
}
