Preparables
===========

Preparables can be used as a base for creation of an object graph.
They help to define requirements.

A possible usecase are controller classes.

Basics
------

Preparables are objects that can define a list of requirements
that they need to run.

This requirements can be collected by the Preparer.
The Preparer then collects this requirements and then hands them over to
specific Resolvers and fullfills then the requirements witht he results
from the Resolver.

A Resolver is a specialiced object that can turn a specific Requirement object into
a result for the Preparable.


