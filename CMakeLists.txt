cmake_minimum_required(VERSION 3.17)
project(Election_Simulator)

set(CMAKE_CXX_STANDARD 14)

add_executable(Election_Simulator
        main.cpp
        election.cpp
        election.h
        )
