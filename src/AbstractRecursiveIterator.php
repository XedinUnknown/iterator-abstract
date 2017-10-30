<?php

namespace Dhii\Iterator;

use Dhii\Iterator\RecursiveIteratorInterface as R;
use Iterator;
use Traversable;

/**
 * Common functionality for iterators which visit nodes more than 1 level deep.
 *
 * @since [*next-version*]
 */
abstract class AbstractRecursiveIterator extends AbstractIterator
{
    /**
     * The stack of parents needed to maintain hierarchy path trace.
     *
     * @since [*next-version*]
     *
     * @var Iterator[]|array[]
     */
    protected $parents;

    /**
     * The path segments made up of the keys of the parents on the stack.
     *
     * @since [*next-version*]
     *
     * @var array
     */
    protected $pathSegments;

    /**
     * Resets the values.
     *
     * @since [*next-version*]
     */
    protected function _construct()
    {
        $this->_resetParents();

        parent::_construct();
    }

    /**
     * Retrieves the current path segments.
     *
     * @since [*next-version*]
     *
     * @return array
     */
    protected function _getPathSegments()
    {
        return $this->pathSegments;
    }

    /**
     * Pushes a path segment to the path stack.
     *
     * @since [*next-version*]
     *
     * @param string $segment The path segment to add.
     */
    protected function _pushPathSegment($segment)
    {
        array_push($this->pathSegments, $segment);
    }

    /**
     * Removes the last added path segment from the path stack.
     *
     * @since [*next-version*]
     */
    protected function _popPathSegment()
    {
        array_pop($this->pathSegments);
    }

    /**
     * Adds an iterable parent onto the stack.
     *
     * The stack is there to maintain a trace of hierarchy.
     *
     * @since [*next-version*]
     *
     * @param Iterator|array $parent The parent.
     */
    protected function _pushParent(&$parent)
    {
        $children    = &$this->_getElementChildren($parent);
        $pathSegment = $this->_getElementPathSegment(null, $parent);

        $this->_pushPathSegment($pathSegment);

        reset($children);
        array_unshift($this->parents, $children);
    }

    /**
     * Removes an iterable parent from the stack.
     *
     * The stack is there to maintain a trace of hierarchy.
     *
     * @since [*next-version*]
     */
    protected function _popParent()
    {
        $this->_popPathSegment();

        array_shift($this->parents);
    }

    /**
     * Checks if there are iterable parents on the stack.
     *
     * @since [*next-version*]
     *
     * @return bool True if there is at least one iterable parent on the stack, false if there are none.
     */
    protected function _hasParents()
    {
        return count($this->parents) > 0;
    }

    /**
     * Returns the parent stack to its original state.
     *
     * @since [*next-version*]
     *
     * @return $this
     */
    protected function _resetParents()
    {
        $this->parents      = [];
        $this->pathSegments = [];

        return $this;
    }

    /**
     * Retrieves the parent which is at the top of the stack.
     *
     * @since [*next-version*]
     *
     * @return array|Iterator The iterable parent.
     */
    protected function &_getTopmostParent()
    {
        if (isset($this->parents[0])) {
            return $this->parents[0];
        }

        // Only variables may be returned by reference
        $empty = [];

        return $empty;
    }

    /**
     * Retrieves the iterable that this iterator should be iterating over.
     *
     * @since [*next-version*]
     *
     * @return Traversable|array The iterable.
     */
    protected function &_getCurrentIterable()
    {
        $iterable = &$this->_getTopmostParent();

        return $iterable;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _nextIteration()
    {
        // Ensure that there are items on the stack
        if (!$this->_hasParents()) {
            return $this->_createIteration(null, null);
        }

        // Get current top item on the stack and its current iteration entry
        $parent  = &$this->_getCurrentIterable();
        $current = $this->_createCurrentIteration($parent);

        // Reached end of current iterable
        if ($current->getKey() === null) {
            return $this->_backtrackLoop();
        }

        // Element is a leaf
        if (!$this->_isElementHasChildren($current->getValue())) {
            next($parent);

            return $current;
        }

        // Element is not a leaf; push to stack
        $children = $current->getValue();
        $this->_pushParent($children);

        if ($this->_isMode(R::MODE_SELF_FIRST)) {
            return $current;
        }

        return $this->_nextIteration();
    }

    /**
     * Backtracks up one parent, yielding the parent or resuming the loop, whichever is appropriate.
     *
     * @since [*next-version*]
     *
     * @return IterationInterface
     */
    protected function _backtrackLoop()
    {
        $this->_popParent();

        if (!$this->_hasParents()) {
            return $this->_createIteration(null, null);
        }

        $parent  = &$this->_getCurrentIterable();
        $current = $this->_createCurrentIteration($parent);
        next($parent);

        if ($this->_isMode(R::MODE_CHILD_FIRST)) {
            return $current;
        }

        return $this->_nextIteration();
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _resetIteration()
    {
        $this->_resetParents();
        $this->_pushParent($this->_getInitialParentIterable());

        return $this->_nextIteration();
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _valid()
    {
        return $this->_hasParents();
    }

    /**
     * Creates an iteration instance for the current state of a given iterable.
     *
     * @since [*next-version*]
     *
     * @param array|Traversable $iterable The iterable.
     *
     * @return IterationInterface
     */
    protected function _createCurrentIteration(&$iterable)
    {
        $key  = $this->_getCurrentIterableKey($iterable);
        $val  = $this->_getCurrentIterableValue($iterable);
        $path = $this->_getCurrentPath($key, $val);

        return $this->_createIteration($key, $val, $path);
    }

    /**
     * Retrieves the current path.
     *
     * @since [*next-version*]
     *
     * @param string|int $key   The current element key.
     * @param mixed      $value The current element value.
     *
     * @return array
     */
    protected function _getCurrentPath($key, $value)
    {
        $path   = $this->_getPathSegments();
        $path[] = $this->_getElementPathSegment($key, $value);

        return array_filter($path);
    }

    /**
     * Retrieves the key for the current element of an iterable.
     *
     * @since [*next-version*]
     *
     * @param array|Traversable $iterable The iterable.
     *
     * @return string|int|null The current key.
     */
    abstract protected function _getCurrentIterableKey(&$iterable);

    /**
     * Retrieves the value for the current element of an iterable.
     *
     * @since [*next-version*]
     *
     * @param array|Traversable $iterable The iterable.
     *
     * @return mixed The current value.
     */
    abstract protected function _getCurrentIterableValue(&$iterable);

    /**
     * Retrieves the single path segment for a specific element.
     *
     * @since [*next-version*]
     *
     * @param string|int $key   The element key.
     * @param mixed      $value The element value.
     *
     * @return string|null The path segment string or null for no path segment.
     */
    abstract protected function _getElementPathSegment($key, $value);

    /**
     * Creates a new iteration.
     *
     * @since [*next-version*]
     *
     * @return IterationInterface The new iteration.
     */
    protected function _createIteration($key, $value, $pathSegments = [])
    {
        return $this->_createRecursiveIteration($key, $value, $pathSegments);
    }

    /**
     * Creates a new iteration.
     *
     * @since [*next-version*]
     *
     * @param string|int $key          The iteration key.
     * @param mixed      $value        The iteration value.
     * @param array      $pathSegments The path.
     *
     * @return IterationInterface The new iteration.
     */
    abstract protected function _createRecursiveIteration($key, $value, $pathSegments = []);

    /**
     * Retrieves the initial parent iterable.
     *
     * The initial parent is the top-most iterable that will be pushed on to the stack when the iterator is reset.
     *
     * @since [*next-version*]
     *
     * @return array|Traversable
     */
    abstract protected function &_getInitialParentIterable();

    /**
     * Determines if an element has children that this iterator could recurse into.
     *
     * @since [*next-version*]
     *
     * @param mixed $value The element to check.
     *
     * @return bool True of the element has children; false otherwise.
     */
    abstract protected function _isElementHasChildren($value);

    /**
     * Retrieves the children of a element.
     *
     * @since [*next-version*]
     *
     * @param mixed $value The element whose children to retrieve.
     *
     * @return array|Traversable The children of the element.
     */
    abstract protected function &_getElementChildren($value);

    /**
     * Determines if the currently selected modes include a specific mode.
     *
     * @since [*next-version*]
     *
     * @param int $mode The mode to check for.
     *
     * @return bool True if mode selected; otherwise, false.
     */
    abstract protected function _isMode($mode);
}
