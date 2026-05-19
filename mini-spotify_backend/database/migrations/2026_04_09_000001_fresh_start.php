<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('avatar_path')->nullable()->after('role');
            });
        }

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
			$table->string('name')->unique();
			$table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
			$table->string('name')->unique();
			$table->timestamps();
        });

        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->uuid('user_uid')->nullable();
            $table->unique('user_uid');
            $table->foreign('user_uid')->references('uid')->on('users')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('spotify_artist_id', 64)->nullable()->index();
			$table->string('image_path')->nullable();
            $table->integer('followers_count')->default(0);
			$table->timestamps();
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('artist_id')->constrained()->onDelete('cascade');
            $table->string('spotify_album_id', 64)->nullable()->index();
			$table->string('cover_image_path')->nullable();
			$table->timestamps();
        });

        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('artist_id')->constrained()->onDelete('cascade');
			$table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('genre_id')->constrained();
            $table->string('spotify_track_id', 64)->nullable()->index();
			$table->string('audio_path')->nullable();
			$table->integer('duration')->nullable();
			$table->unsignedBigInteger('play_count')->default(0);
            $table->decimal('tempo', 8, 2)->nullable();
            $table->decimal('energy', 5, 4)->nullable();
            $table->decimal('danceability', 5, 4)->nullable();
            $table->decimal('valence', 5, 4)->nullable();
            $table->unsignedInteger('popularity')->nullable();
            $table->date('release_date')->nullable();
			$table->timestamps();
        });

        Schema::create('track_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->uuid('user_uid');
            $table->text('body');
            $table->unsignedInteger('timestamp_seconds');
            $table->timestamps();

            $table->foreign('user_uid')->references('uid')->on('users')->cascadeOnDelete();
            $table->index(['song_id', 'timestamp_seconds', 'created_at']);
            $table->index('user_uid');
        });

        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->uuid('user_uid');
            $table->foreign('user_uid')->references('uid')->on('users')->nullOnDelete();
			$table->string('cover_image_path')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
        });
        Schema::create('playlist_song', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
            $table->foreignId('song_id')->constrained()->onDelete('cascade');
			$table->unique(['playlist_id', 'song_id']);
        });

        Schema::create('liked_albums', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uid');
            $table->foreignId('album_id');
            $table->timestamps();

            $table->unique(['user_uid', 'album_id']);
            $table->index('user_uid');
            $table->index('album_id');

            $table->foreign('user_uid')->references('uid')->on('users')->cascadeOnDelete();
            $table->foreign('album_id')->references('id')->on('albums')->cascadeOnDelete();
        });

        Schema::create('liked_playlists', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uid');
            $table->foreignId('playlist_id');
            $table->timestamps();

            $table->unique(['user_uid', 'playlist_id']);
            $table->index('user_uid');
            $table->index('playlist_id');

            $table->foreign('user_uid')->references('uid')->on('users')->cascadeOnDelete();
            $table->foreign('playlist_id')->references('id')->on('playlists')->cascadeOnDelete();
        });

        Schema::create('liked_artists', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uid');
            $table->foreignId('artist_id');
            $table->timestamps();

            $table->unique(['user_uid', 'artist_id']);
            $table->index('user_uid');
            $table->index('artist_id');

            $table->foreign('user_uid')->references('uid')->on('users')->cascadeOnDelete();
            $table->foreign('artist_id')->references('id')->on('artists')->cascadeOnDelete();
        });

        Schema::create('followed_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('follower_uid');
            $table->uuid('followed_uid');
            $table->timestamps();

            $table->unique(['follower_uid', 'followed_uid']);
            $table->index('follower_uid');
            $table->index('followed_uid');

            $table->foreign('follower_uid')->references('uid')->on('users')->cascadeOnDelete();
            $table->foreign('followed_uid')->references('uid')->on('users')->cascadeOnDelete();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('direct');
            $table->string('direct_key')->nullable()->unique();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_uid');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_uid']);
            $table->index('conversation_id');
            $table->index('user_uid');
            $table->foreign('user_uid')->references('uid')->on('users')->cascadeOnDelete();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->uuid('sender_uid');
            $table->string('message_type')->default('text');
            $table->text('encrypted_body');
            $table->json('shared_item')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_uid');
            $table->foreign('sender_uid')->references('uid')->on('users')->cascadeOnDelete();
        });

	}

	public function down(): void
	{
        Schema::dropIfExists('messages');
		Schema::dropIfExists('conversation_participants');
		Schema::dropIfExists('conversations');
        Schema::dropIfExists('followed_users');
		Schema::dropIfExists('liked_artists');
		Schema::dropIfExists('liked_playlists');
		Schema::dropIfExists('liked_albums');
		Schema::dropIfExists('playlist_song');
		Schema::dropIfExists('playlists');
        Schema::dropIfExists('track_comments');
		Schema::dropIfExists('songs');
		Schema::dropIfExists('albums');
		Schema::dropIfExists('artists');
		Schema::dropIfExists('countries');
		Schema::dropIfExists('genres');

        if (Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('avatar_path');
            });
        }
	}
};
