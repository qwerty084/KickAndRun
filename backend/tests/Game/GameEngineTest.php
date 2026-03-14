<?php

namespace App\Tests\Game;

use App\Game\GameEngine;
use App\Game\GameState;
use App\Game\PiecePosition;
use App\Game\PlayerColor;
use App\Game\Exception\InvalidMoveException;
use App\Game\Exception\NotYourTurnException;
use PHPUnit\Framework\TestCase;

class GameEngineTest extends TestCase
{
    private GameEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new GameEngine();
    }

    // --- Initialization ---

    public function testInitializeGameCreatesAllPiecesInBase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());

        $this->assertCount(4, $state->players);
        $this->assertSame(0, $state->currentPlayerIndex);
        $this->assertSame(GameState::PHASE_ROLLING, $state->phase);
        $this->assertNull($state->lastDiceRoll);
        $this->assertNull($state->winner);
        $this->assertSame(1, $state->turnNumber);

        foreach (PlayerColor::inOrder() as $color) {
            $pieces = $state->piecesOf($color);
            $this->assertCount(4, $pieces);
            foreach ($pieces as $piece) {
                $this->assertTrue($piece->isBase());
            }
        }
    }

    public function testInitializeTwoPlayerGame(): void
    {
        $state = $this->engine->initializeGame([PlayerColor::Green, PlayerColor::Red]);

        $this->assertCount(2, $state->players);
        $this->assertSame(PlayerColor::Green, $state->currentPlayer());
    }

    // --- Dice Rolling ---

    public function testRollDiceWithForcedValue(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $newState = $this->engine->rollDice($state, PlayerColor::Green, 3);

        // All in base, rolled non-6: should still be in rolling phase (has attempts left)
        $this->assertSame(3, $newState->lastDiceRoll);
    }

    public function testRollSixAllInBaseMovesToMovingPhase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $newState = $this->engine->rollDice($state, PlayerColor::Green, 6);

        $this->assertSame(GameState::PHASE_MOVING, $newState->phase);
        $this->assertSame(6, $newState->lastDiceRoll);
    }

    public function testWrongPlayerCannotRoll(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());

        $this->expectException(NotYourTurnException::class);
        $this->engine->rollDice($state, PlayerColor::Yellow, 3);
    }

    public function testCannotRollInMovingPhase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);

        $this->assertSame(GameState::PHASE_MOVING, $state->phase);

        $this->expectException(InvalidMoveException::class);
        $this->engine->rollDice($state, PlayerColor::Green, 3);
    }

    // --- 3 Roll Attempts When All In Base ---

    public function testThreeRollAttemptsWhenAllInBase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());

        // First attempt: non-6
        $state = $this->engine->rollDice($state, PlayerColor::Green, 2);
        $this->assertSame(GameState::PHASE_ROLLING, $state->phase);
        $this->assertSame(PlayerColor::Green, $state->currentPlayer());

        // Second attempt: non-6
        $state = $this->engine->rollDice($state, PlayerColor::Green, 4);
        $this->assertSame(GameState::PHASE_ROLLING, $state->phase);
        $this->assertSame(PlayerColor::Green, $state->currentPlayer());

        // Third attempt: non-6 -> turn passes
        $state = $this->engine->rollDice($state, PlayerColor::Green, 1);
        $this->assertSame(GameState::PHASE_ROLLING, $state->phase);
        $this->assertSame(PlayerColor::Yellow, $state->currentPlayer());
    }

    public function testThreeRollAttemptsSecondAttemptIsSix(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());

        // First attempt: non-6
        $state = $this->engine->rollDice($state, PlayerColor::Green, 2);
        $this->assertSame(PlayerColor::Green, $state->currentPlayer());

        // Second attempt: 6! -> can move
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $this->assertSame(GameState::PHASE_MOVING, $state->phase);
    }

    // --- Exiting Base ---

    public function testExitBaseOnSix(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);

        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 6);

        // All 4 pieces in base, all can exit to entry position 0
        $this->assertCount(4, $moves);
        foreach ($moves as $move) {
            $this->assertSame('base', $move['from']);
            $this->assertSame('path:0', $move['to']);
        }
    }

    public function testCannotExitBaseWithoutSix(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 3);

        $this->assertEmpty($moves);
    }

    public function testMoveOutOfBase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);

        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertSame('base', $result->from->toString());
        $this->assertSame('path:0', $result->to->toString());
        $this->assertTrue($result->extraTurn); // rolled a 6
        $this->assertTrue($result->newState->piecesOf(PlayerColor::Green)[0]->isPath());
    }

    // --- Movement on Path ---

    public function testMoveAlongPath(): void
    {
        $state = $this->createStateWithPieceOnPath(PlayerColor::Green, 0, 5);

        $state = $this->engine->rollDice($state, PlayerColor::Green, 4);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertSame('path:5', $result->from->toString());
        $this->assertSame('path:1', $result->to->toString());
    }

    public function testPathWrapsAround(): void
    {
        $state = $this->createStateWithPieceOnPath(PlayerColor::Yellow, 0, 2);

        $state = $this->engine->rollDice($state, PlayerColor::Yellow, 4);
        $result = $this->engine->movePiece($state, PlayerColor::Yellow, 0);

        $this->assertSame('path:2', $result->from->toString());
        $this->assertSame('path:38', $result->to->toString());
    }

    // --- Kicking ---

    public function testKickOpponentPiece(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Green piece on path:5, Yellow piece on path:2 (3 steps clockwise from 5)
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(5));
        $state->setPiece(PlayerColor::Yellow, 0, PiecePosition::path(2));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 3);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertNotNull($result->kicked);
        $this->assertSame('yellow', $result->kicked['player']);
        $this->assertSame(0, $result->kicked['pieceIndex']);
        $this->assertSame('path:2', $result->kicked['from']);
        $this->assertSame('base', $result->kicked['to']);

        // Yellow's piece is back in base
        $this->assertTrue($result->newState->piecesOf(PlayerColor::Yellow)[0]->isBase());
    }

    public function testCannotLandOnOwnPiece(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Green pieces at path:5 and path:2 (3 steps clockwise from 5)
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(5));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::path(2));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 3);
        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 3);

        // piece 0 would land on piece 1 at path:2 — should be filtered out
        $moveIndices = array_column($moves, 'pieceIndex');
        $this->assertNotContains(0, $moveIndices);
    }

    // --- Goal Zone ---

    public function testEnterGoalZone(): void
    {
        // Green entry=0, clockwise 38 steps from entry lands on path:2
        // Roll 3 -> 38+3=41 -> goalIndex = 1 -> goal:1
        $state = $this->createStateWithPieceOnPath(PlayerColor::Green, 0, 2);

        $state = $this->engine->rollDice($state, PlayerColor::Green, 3);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertSame('path:2', $result->from->toString());
        $this->assertSame('goal:1', $result->to->toString());
    }

    public function testCannotOvershootGoal(): void
    {
        // Green piece at path:2 (38 clockwise steps from entry), needs at most 5 to fill goal:3
        $state = $this->createStateWithPieceOnPath(PlayerColor::Green, 0, 2);

        // Roll 6 would try to land at goal:4 — invalid
        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 6);

        // Should have no valid moves for piece 0
        $moveForPiece0 = array_filter($moves, fn($m) => $m['pieceIndex'] === 0);
        $this->assertEmpty($moveForPiece0);
    }

    public function testMoveWithinGoalZone(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::goal(0));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 2);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertSame('goal:0', $result->from->toString());
        $this->assertSame('goal:2', $result->to->toString());
    }

    public function testCannotOvershootWithinGoal(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::goal(2));

        // Roll 3 would put it at goal:5, which exceeds 3
        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 3);
        $moveForPiece0 = array_filter($moves, fn($m) => $m['pieceIndex'] === 0);
        $this->assertEmpty($moveForPiece0);
    }

    // --- Extra Turn on 6 ---

    public function testExtraTurnOnSix(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // All pieces on path — no base pieces to trigger must-exit rule
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(35));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::path(30));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::path(25));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::path(20));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertTrue($result->extraTurn);
        $this->assertSame(PlayerColor::Green, $result->newState->currentPlayer());
        $this->assertSame(GameState::PHASE_ROLLING, $result->newState->phase);
    }

    public function testNoExtraTurnOnNonSix(): void
    {
        $state = $this->createStateWithPieceOnPath(PlayerColor::Green, 0, 5);

        $state = $this->engine->rollDice($state, PlayerColor::Green, 3);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertFalse($result->extraTurn);
        $this->assertSame(PlayerColor::Yellow, $result->newState->currentPlayer());
    }

    // --- Three Consecutive Sixes ---

    public function testThreeConsecutiveSixesForfeitsTurn(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // All pieces on path — no base pieces to trigger must-exit rule
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(35));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::path(30));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::path(25));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::path(20));

        // First 6: move piece 0 from path:35 -> path:29 (clockwise)
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);
        $state = $result->newState;
        $this->assertTrue($result->extraTurn);

        // Second 6: move piece 0 from path:29 -> path:23 (clockwise)
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);
        $state = $result->newState;
        $this->assertTrue($result->extraTurn);

        // Third 6: piece goes back to base, turn passes
        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertFalse($result->extraTurn);
        $this->assertSame(PlayerColor::Yellow, $result->newState->currentPlayer());
        $this->assertTrue($result->newState->piecesOf(PlayerColor::Green)[0]->isBase());
    }

    // --- Must Exit Base Rule ---

    public function testMustExitBaseIfPossible(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Green: piece 0 on path, pieces 1-3 in base
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(5));

        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 6);

        // Must exit base: only base pieces should be movable (not the one on path)
        foreach ($moves as $move) {
            $this->assertSame('base', $move['from']);
        }
    }

    public function testCanMovePieceOnPathWhenNoPiecesInBase(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(5));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::path(10));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::path(20));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::path(30));

        $moves = $this->engine->getValidMoves($state, PlayerColor::Green, 3);

        $this->assertNotEmpty($moves);
        foreach ($moves as $move) {
            $this->assertStringStartsWith('path:', $move['from']);
        }
    }

    // --- No Valid Moves ---

    public function testNoValidMovesAutoSkipsTurn(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Green: all pieces in goal zone, can't move with roll of 5
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::goal(3));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::goal(2));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::goal(1));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::goal(0));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 5);

        // Should auto-advance to Yellow
        $this->assertSame(PlayerColor::Yellow, $state->currentPlayer());
    }

    // --- Win Detection ---

    public function testWinDetection(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Green: 3 pieces in goal, 1 about to enter last goal spot
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::goal(0));
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::goal(1));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::goal(2));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::path(38));

        // path:38 for Green: stepsFromEntry = (38-0+40)%40 = 38; roll 3 -> 38+3=41 -> goal:1
        // But goal:1 is occupied. Let's use different positions.
        // Green entry=0, path:37, roll 4 -> 37+4=41 -> goalIndex = 41-40 = 1 (occupied!)
        // Let me use: piece 3 at goal position that leads to winning.
        // Simplest: 3 pieces in goal:0-2, piece 3 at goal:2 moving to goal:3
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::goal(2));
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::goal(3));

        // Now 0=goal:0, 1=goal:1, 2=goal:3, 3=goal:2 — all in goal, should win immediately
        $winner = $this->engine->checkWinner($state);
        $this->assertSame(PlayerColor::Green, $winner);
    }

    public function testNoWinnerWhenNotAllFinished(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::finished());
        $state->setPiece(PlayerColor::Green, 1, PiecePosition::finished());
        $state->setPiece(PlayerColor::Green, 2, PiecePosition::path(10));
        $state->setPiece(PlayerColor::Green, 3, PiecePosition::goal(2));

        $winner = $this->engine->checkWinner($state);

        $this->assertNull($winner);
    }

    // --- Serialization ---

    public function testGameStateSerializationRoundTrip(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece(PlayerColor::Green, 0, PiecePosition::path(5));
        $state->setPiece(PlayerColor::Yellow, 1, PiecePosition::goal(2));
        $state->setPiece(PlayerColor::Red, 2, PiecePosition::finished());
        $state->lastDiceRoll = 4;
        $state->consecutiveSixes = 1;

        $array = $state->toArray();
        $restored = GameState::fromArray($array);

        $this->assertSame($state->currentPlayerIndex, $restored->currentPlayerIndex);
        $this->assertSame($state->lastDiceRoll, $restored->lastDiceRoll);
        $this->assertSame($state->phase, $restored->phase);
        $this->assertSame($state->consecutiveSixes, $restored->consecutiveSixes);
        $this->assertSame($state->turnNumber, $restored->turnNumber);

        // Check positions survived round trip
        $this->assertTrue($restored->piecesOf(PlayerColor::Green)[0]->equals(PiecePosition::path(5)));
        $this->assertTrue($restored->piecesOf(PlayerColor::Yellow)[1]->equals(PiecePosition::goal(2)));
        $this->assertTrue($restored->piecesOf(PlayerColor::Red)[2]->isFinished());
    }

    // --- Game Over Prevents Actions ---

    public function testCannotRollAfterGameFinished(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->phase = GameState::PHASE_FINISHED;
        $state->winner = PlayerColor::Green;

        $this->expectException(InvalidMoveException::class);
        $this->engine->rollDice($state, PlayerColor::Green, 3);
    }

    public function testCannotMoveAfterGameFinished(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->phase = GameState::PHASE_FINISHED;
        $state->winner = PlayerColor::Green;

        $this->expectException(InvalidMoveException::class);
        $this->engine->movePiece($state, PlayerColor::Green, 0);
    }

    // --- Player Color Entry Positions ---

    public function testYellowEntryPosition(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->currentPlayerIndex = 1; // Yellow's turn

        $state = $this->engine->rollDice($state, PlayerColor::Yellow, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Yellow, 0);

        $this->assertSame('path:10', $result->to->toString());
    }

    public function testRedEntryPosition(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->currentPlayerIndex = 2; // Red's turn

        $state = $this->engine->rollDice($state, PlayerColor::Red, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Red, 0);

        $this->assertSame('path:20', $result->to->toString());
    }

    public function testBlackEntryPosition(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->currentPlayerIndex = 3; // Black's turn

        $state = $this->engine->rollDice($state, PlayerColor::Black, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Black, 0);

        $this->assertSame('path:30', $result->to->toString());
    }

    // --- Yellow Goal Zone Entry ---

    public function testYellowEntersGoalZone(): void
    {
        // Yellow entry=10, goal entry after position 11 (clockwise)
        // A piece at path:13 has traveled (10-13+40)%40 = 37 steps clockwise
        // Roll 4 -> 37+4=41 -> goalIndex = 41-40 = 1 -> goal:1
        $state = $this->createStateWithPieceOnPath(PlayerColor::Yellow, 0, 13);

        $state = $this->engine->rollDice($state, PlayerColor::Yellow, 4);
        $result = $this->engine->movePiece($state, PlayerColor::Yellow, 0);

        $this->assertSame('goal:1', $result->to->toString());
    }

    // --- Kick on Base Exit ---

    public function testKickOnBaseExit(): void
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        // Yellow piece sitting on Green's entry (path:0)
        $state->setPiece(PlayerColor::Yellow, 0, PiecePosition::path(0));

        $state = $this->engine->rollDice($state, PlayerColor::Green, 6);
        $result = $this->engine->movePiece($state, PlayerColor::Green, 0);

        $this->assertNotNull($result->kicked);
        $this->assertSame('yellow', $result->kicked['player']);
        $this->assertTrue($result->newState->piecesOf(PlayerColor::Yellow)[0]->isBase());
    }

    // --- Helpers ---

    /**
     * Create a state where a specific player has one piece on the path and the rest in base.
     * Sets it to that player's turn.
     */
    private function createStateWithPieceOnPath(PlayerColor $player, int $pieceIndex, int $pathPosition): GameState
    {
        $state = $this->engine->initializeGame(PlayerColor::inOrder());
        $state->setPiece($player, $pieceIndex, PiecePosition::path($pathPosition));

        // Set correct player's turn
        $playerIndex = array_search($player, PlayerColor::inOrder(), true);
        $state->currentPlayerIndex = $playerIndex;

        return $state;
    }
}
