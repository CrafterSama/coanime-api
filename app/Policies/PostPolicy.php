<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->created_at && is_null($post->deleted_at)) {
            return true;
        }

        return $user && $user->is($post->users);
    }

    /**
     * Determine whether the user can view the post's author.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAuthor(?User $user, Post $post)
    {
        return $this->view($user, $post);
    }

    /**
     * Determine whether the user can view the post's tags.
     *
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewTags(?User $user, Post $post)
    {
        return $this->view($user, $post);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        //
    }
}
