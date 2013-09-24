Preparables
===========

[![Build Status](https://travis-ci.org/P2EE/preparables.png?branch=master)](https://travis-ci.org/P2EE/preparables)
[![Dependency Status](http://www.versioneye.com/user/projects/524158c7632bac486600582a/badge.png)](http://www.versioneye.com/user/projects/524158c7632bac486600582a)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/P2EE/preparables/badges/quality-score.png?s=9f70d3ad93f4d9ef259596718f00387f6b4e506a)](https://scrutinizer-ci.com/g/P2EE/preparables/)
[![Code Coverage](https://scrutinizer-ci.com/g/P2EE/preparables/badges/coverage.png?s=54a54725a9e956b1acd2ed16cfa30fb6f84da2b5)](https://scrutinizer-ci.com/g/P2EE/preparables/)

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


