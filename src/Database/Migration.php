<?php

declare(strict_types=1);

namespace BXCode\Database;

/**
 * Base class for migrations. A migration file returns an anonymous subclass:
 *
 *   return new class extends \BXCode\Database\Migration {
 *       public function up(Connection $db): void   { ... }
 *       public function down(Connection $db): void { ... }
 *   };
 *
 * Migrations write portable SQL via the Connection directly. A cross-database
 * schema builder (Blueprint) is intentionally not part of Phase 2 — add one
 * later if hand-written SQL per driver becomes a burden.
 */
abstract class Migration
{
    /** Apply the change. */
    abstract public function up(Connection $db): void;

    /** Reverse the change. */
    abstract public function down(Connection $db): void;
}
